<?php

namespace App\Services\Shipping;

use App\Models\Rider;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
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

        if ($status->isTerminal()) {
            return ['actions' => [], 'blocked' => 'finished', 'held_by' => null];
        }

        $holder = $shipment->rider_id ? (int) $shipment->rider_id : null;

        if ($holder !== null && $holder !== $rider->id) {
            return ['actions' => [], 'blocked' => 'held_by_other', 'held_by' => $shipment->rider?->name];
        }

        $actions = match (true) {
            $holder !== null && $status === ShipmentStatus::OUT_FOR_DELIVERY => [
                RiderAction::DELIVERED,
                RiderAction::ATTEMPT_FAILED,
            ],
            // Failed earlier: try again now, fail again, or take it out on a
            // later run.
            $holder !== null && $status === ShipmentStatus::ATTEMPT_FAIL => [
                RiderAction::DELIVERED,
                RiderAction::ATTEMPT_FAILED,
                RiderAction::OUT_FOR_DELIVERY,
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

                $reason = FailedAttemptReason::tryFrom((string) ($input['reason'] ?? ''));
                $occurredAt = $this->occurredAt($input['occurred_at'] ?? null);
                $statusBefore = $shipment->status;

                $event = ShipmentEvent::create([
                    'shipment_id' => $shipment->id,
                    'rider_id' => $rider->id,
                    'action' => $action->value,
                    'status_before' => $statusBefore,
                    'status_after' => $action->targetStatus()->value,
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

                $shipment->addTrackingEvent(new TrackingEvent(
                    status: $action->targetStatus(),
                    description: $description,
                    location: $shipment->receiverDetails()['city'] ?: null,
                    // Milliseconds: two updates in the same second still sort
                    // newest-first (tracking_history sorts on this string).
                    timestamp: $occurredAt->copy()->setTimezone(config('app.business_timezone', 'Asia/Riyadh'))->format('Y-m-d\\TH:i:s.vP'),
                    rawStatus: strtoupper($action->value),
                    staffName: $rider->publicName(),
                ));

                [$applied, $extra] = $shipment->resolveTrackingStatus($action->targetStatus());

                $shipment->fill([
                    'rider_id' => $rider->id,
                    'courier_status' => $action->value,
                    'courier_status_description' => $description,
                ] + $extra);

                if ($action === RiderAction::DELIVERED) {
                    // markDelivered() sets the status itself, and only
                    // notifies admins when it wasn't delivered already.
                    $shipment->save();
                    $shipment->markDelivered();
                    $this->recordCodCollected($shipment, $event);
                } else {
                    $shipment->fill(['status' => $applied->value])->save();
                }

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
    private function occurredAt(?string $value): Carbon
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
