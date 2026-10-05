<?php

namespace App\Services\Shipping;

use App\Models\Rider;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\Riders\RiderPay;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
use App\Services\Shipping\Enums\FailedAttemptReason;
use App\Services\Shipping\Enums\RiderAction;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one path a KSA Express status update takes, whoever makes it.
 *
 * Writes the full record to shipment_events, a public-safe line to
 * shipments.tracking_history (what /track shows), moves the status through
 * Shipment::resolveTrackingStatus() like every courier webhook does, and on
 * delivery runs the existing Shipment::markDelivered() side effects (order
 * fulfilled, admins notified).
 *
 * A parcel that ends returned or cancelled while a rider has it isn't done
 * until the hub has it back: it stays in the rider's hands, with "returned
 * to hub" as the only update left (Shipment::awaitsHandBack()).
 */
class ShipmentEventRecorder
{
    /** Private disk; photos are streamed to admins, never public. */
    public const PHOTO_DISK = 'local';

    /**
     * What the update screen offers for this parcel, first option pre-selected.
     *
     * @return array{actions: list<RiderAction>, blocked: ?string, held_by: ?string}
     */
    public function optionsFor(Shipment $shipment, Rider $rider): array
    {
        if ($shipment->courier !== KsaDropExpressDriver::KEY) {
            return ['actions' => [], 'blocked' => 'not_ksa_express', 'held_by' => null];
        }

        $status = $shipment->status_enum;
        $holder = $shipment->rider_id ? (int) $shipment->rider_id : null;

        if ($status->isTerminal()) {
            // Returned or cancelled while out with this rider: all that's
            // left is handing it back.
            return $holder === $rider->id && $shipment->awaitsHandBack()
                ? ['actions' => [RiderAction::RETURNED_TO_HUB], 'blocked' => null, 'held_by' => null]
                : ['actions' => [], 'blocked' => 'finished', 'held_by' => null];
        }

        if ($holder !== null && $holder !== $rider->id) {
            return ['actions' => [], 'blocked' => 'held_by_other', 'held_by' => $shipment->rider?->name];
        }

        $actions = match (true) {
            // With the rider: deliver it, fail the attempt, or end it — the
            // customer won't take it (returned) or called it off (cancelled).
            $holder !== null && $status === ShipmentStatus::OUT_FOR_DELIVERY => [
                RiderAction::DELIVERED,
                RiderAction::ATTEMPT_FAILED,
                RiderAction::RETURNED,
                RiderAction::CANCELLED,
            ],
            // Failed earlier: try again now, fail again, take it out on a
            // later run, or end it.
            $holder !== null && $status === ShipmentStatus::ATTEMPT_FAIL => [
                RiderAction::DELIVERED,
                RiderAction::ATTEMPT_FAILED,
                RiderAction::OUT_FOR_DELIVERY,
                RiderAction::RETURNED,
                RiderAction::CANCELLED,
            ],
            default => [
                RiderAction::OUT_FOR_DELIVERY,
                RiderAction::DELIVERED,
                RiderAction::ATTEMPT_FAILED,
            ],
        };

        return ['actions' => $actions, 'blocked' => null, 'held_by' => null];
    }

    /**
     * @param  array{client_uuid: string, reason?: ?string, note?: ?string, cod_amount?: float|string|null, payment_method?: ?string, recipient_name?: ?string, lat?: float|string|null, lng?: float|string|null, accuracy_m?: int|string|null, entry_method?: ?string, occurred_at?: ?string}  $input
     *
     * @throws RiderActionRefused
     */
    public function record(Shipment $shipment, Rider $rider, RiderAction $action, array $input, ?UploadedFile $photo = null): ShipmentEvent
    {
        // A retry of an update that already went through (the response was
        // lost on a weak signal, or a double tap) must not record twice.
        if ($existing = $this->alreadyRecorded($input['client_uuid'], $rider)) {
            return $existing;
        }

        $photoPath = $photo ? $this->storePhoto($photo, $shipment) : null;

        try {
            return DB::transaction(function () use ($shipment, $rider, $action, $input, $photoPath) {
                $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

                $options = $this->optionsFor($shipment, $rider);

                if (! in_array($action, $options['actions'], true)) {
                    throw new RiderActionRefused(
                        $options['blocked'] ?? 'not_allowed',
                        $this->refusalMessage($options['blocked'], $shipment, $options['held_by']),
                        $options['held_by'],
                    );
                }

                $reason = $action->needsReason() ? FailedAttemptReason::tryFrom((string) ($input['reason'] ?? '')) : null;
                $occurredAt = self::occurredAt($input['occurred_at'] ?? null);
                $statusBefore = $shipment->status;
                $target = $action->targetStatus($shipment->status_enum);

                $event = ShipmentEvent::create([
                    'shipment_id' => $shipment->id,
                    'rider_id' => $rider->id,
                    'action' => $action->value,
                    'status_before' => $statusBefore,
                    'status_after' => $target->value,
                    'reason' => $reason?->value,
                    'note' => $input['note'] ?? null,
                    'cod_amount' => $action === RiderAction::DELIVERED ? ($input['cod_amount'] ?? null) : null,
                    'payment_method' => $action === RiderAction::DELIVERED ? ($input['payment_method'] ?? null) : null,
                    'recipient_name' => $action === RiderAction::DELIVERED ? ($input['recipient_name'] ?? null) : null,
                    'photo_path' => $photoPath,
                    'lat' => $input['lat'] ?? null,
                    'lng' => $input['lng'] ?? null,
                    'accuracy_m' => isset($input['accuracy_m']) ? (int) $input['accuracy_m'] : null,
                    'entry_method' => $input['entry_method'] ?? null,
                    'occurred_at' => $occurredAt,
                    'client_uuid' => $input['client_uuid'],
                ]);

                $description = $action->publicDescription($rider->publicName(), $reason);

                $shipment->addTrackingEvent($this->trackingEvent($shipment, $action, $target, $description, $occurredAt, $rider->publicName()));

                [$applied, $extra] = $shipment->resolveTrackingStatus($target);

                $shipment->fill([
                    'rider_id' => $rider->id,
                    'courier_status' => $action->value,
                    'courier_status_description' => $description,
                ] + $extra);

                $this->apply($shipment, $action, $applied, $occurredAt, $this->endingReason($reason, $input['note'] ?? null, $rider->publicName()));

                if ($action === RiderAction::DELIVERED) {
                    $this->recordCodCollected($shipment, $event);
                }

                RiderPay::record($rider, $event);

                return $event;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The same update raced in twice; the other request recorded it.
            $this->deletePhoto($photoPath);

            return $this->alreadyRecorded($input['client_uuid'], $rider) ?? throw $e;
        } catch (\Throwable $e) {
            $this->deletePhoto($photoPath);

            throw $e;
        }
    }

    /**
     * The same updates made from the portal, for a rider who can't make them:
     * marking a parcel returned, or taking a returned or cancelled one back
     * at the hub (lost phone, unreadable label).
     *
     * @throws RiderActionRefused
     */
    public function recordByStaff(Shipment $shipment, User $user, RiderAction $action, ?string $note = null): ShipmentEvent
    {
        return DB::transaction(function () use ($shipment, $user, $action, $note) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            $allowed = $shipment->courier === KsaDropExpressDriver::KEY && match ($action) {
                RiderAction::RETURNED => ! $shipment->status_enum->isTerminal(),
                RiderAction::RETURNED_TO_HUB => $shipment->awaitsHandBack(),
                default => false,
            };

            if (! $allowed) {
                throw new RiderActionRefused('not_allowed', $this->refusalMessage(null, $shipment, null));
            }

            $occurredAt = now();
            $target = $action->targetStatus($shipment->status_enum);

            $event = ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'user_id' => $user->id,
                'action' => $action->value,
                'status_before' => $shipment->status,
                'status_after' => $target->value,
                'note' => $note,
                'occurred_at' => $occurredAt,
                'client_uuid' => (string) Str::uuid(),
            ]);

            $description = $action->publicDescription('');

            $shipment->addTrackingEvent($this->trackingEvent($shipment, $action, $target, $description, $occurredAt));

            [$applied, $extra] = $shipment->resolveTrackingStatus($target);

            $shipment->fill([
                'courier_status' => $action->value,
                'courier_status_description' => $description,
            ] + $extra);

            $this->apply($shipment, $action, $applied, $occurredAt, (string) $note);

            return $event;
        });
    }

    /**
     * Save the update onto the shipment, through the same endings every
     * courier's updates take.
     */
    private function apply(Shipment $shipment, RiderAction $action, ShipmentStatus $applied, Carbon $occurredAt, string $endingReason): void
    {
        if ($action === RiderAction::DELIVERED) {
            // markDelivered() sets the status itself, and only notifies
            // admins when it wasn't delivered already.
            $shipment->save();
            $shipment->markDelivered();

            return;
        }

        match ($action) {
            RiderAction::RETURNED => $shipment->markReturned($endingReason),
            RiderAction::CANCELLED => $shipment->markCancelled($endingReason),
            RiderAction::RETURNED_TO_HUB => $shipment->update(['hub_received_at' => $occurredAt]),
            default => $shipment->update(['status' => $applied->value]),
        };
    }

    /**
     * What the portal shows as why a parcel was returned or cancelled.
     */
    private function endingReason(?FailedAttemptReason $reason, ?string $note, string $riderFirstName): string
    {
        return implode(' — ', array_filter([$reason?->label(), $note])) . " (rider {$riderFirstName})";
    }

    private function trackingEvent(Shipment $shipment, RiderAction $action, ShipmentStatus $status, string $description, Carbon $occurredAt, ?string $staffName = null): TrackingEvent
    {
        return new TrackingEvent(
            status: $status,
            description: $description,
            location: $shipment->receiverDetails()['city'] ?: null,
            // Milliseconds: two updates in the same second still sort
            // newest-first (tracking_history sorts on this string).
            timestamp: $occurredAt->copy()->setTimezone(config('app.business_timezone', 'Asia/Riyadh'))->format('Y-m-d\\TH:i:s.vP'),
            rawStatus: strtoupper($action->value),
            staffName: $staffName,
        );
    }

    private function alreadyRecorded(string $clientUuid, Rider $rider): ?ShipmentEvent
    {
        $event = ShipmentEvent::where('client_uuid', $clientUuid)->first();

        if ($event && (int) $event->rider_id !== $rider->id) {
            throw new RiderActionRefused('not_allowed', 'This update was already recorded by someone else.');
        }

        return $event;
    }

    private function recordCodCollected(Shipment $shipment, ShipmentEvent $event): void
    {
        if ($event->cod_amount === null || (float) $event->cod_amount <= 0 || ! $shipment->order) {
            return;
        }

        $shipment->order->update([
            'cod_collected_amount' => $event->cod_amount,
            'cod_collected_at' => $event->occurred_at,
        ]);
    }

    /**
     * The phone's clock, trusted within reason: a phone set to the wrong day
     * shouldn't backdate a delivery by a month or date it in the future.
     */
    public static function occurredAt(?string $value): Carbon
    {
        $now = now();

        try {
            $at = $value ? Carbon::parse($value) : $now;
        } catch (\Throwable) {
            return $now;
        }

        if ($at->gt($now->copy()->addMinutes(5)) || $at->lt($now->copy()->subDays(7))) {
            return $now;
        }

        return $at;
    }

    private function storePhoto(UploadedFile $photo, Shipment $shipment): string
    {
        $extension = $photo->guessExtension() ?: 'jpg';

        return $photo->storeAs(
            'pod/' . now()->format('Y/m'),
            $shipment->id . '-' . Str::uuid() . '.' . $extension,
            self::PHOTO_DISK,
        );
    }

    private function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk(self::PHOTO_DISK)->delete($path);
        }
    }

    private function refusalMessage(?string $blocked, Shipment $shipment, ?string $heldBy): string
    {
        return match ($blocked) {
            'not_ksa_express' => 'This parcel is not a KSA Express shipment.',
            'finished' => "This parcel is already {$shipment->status_label}.",
            'held_by_other' => 'This parcel is with ' . ($heldBy ?: 'another rider') . '.',
            default => 'This update is not allowed for this parcel right now.',
        };
    }
}
