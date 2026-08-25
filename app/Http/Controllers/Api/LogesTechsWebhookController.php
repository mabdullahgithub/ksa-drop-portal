<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectorSetting;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\LogesTechsDriver;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LogesTechs last-mile status webhook — the third courier push handler
 * alongside {@see WebhookController} (J&T) and {@see ImileWebhookController}.
 *
 * Three things make this one different, all confirmed directly with LogesTechs
 * (2026-08-25) since none of it appears in their written documentation:
 *
 *  - **Authentication is a shared username/password echoed in the body.** The
 *    account holder sets a Webhook URL, Username and Password in LogesTechs'
 *    own "Edit customer webhook" panel, and every push carries those values
 *    back as `webhookUsername`/`webhookPassword`. There is no signature to
 *    verify. That makes them a bearer secret in the payload — compared with
 *    hash_equals() in {@see verifyCredentials()}, and redacted before the
 *    payload is ever logged.
 *
 *  - **Each status change is pushed exactly once, with no retry.** LogesTechs:
 *    "only status 200, its send one time". A push we fail to process is gone
 *    for good, so failures are logged loudly (with the full payload, minus the
 *    password) to the dedicated `logestechs_webhooks` channel, and
 *    {@see \App\Console\Commands\SyncShipmentTracking} is the real backstop —
 *    for this courier it is load-bearing, not a nicety.
 *
 *  - **Order matching runs on `supplierInvoice`, not `invoiceNumber`.** A live
 *    test push confirmed `supplierInvoice` echoes back exactly the value we
 *    send on creation, while `invoiceNumber` holds a separate LogesTechs-side
 *    reference we never see at creation time.
 *
 * Idempotency comes from {@see \App\Models\Shipment::addTrackingEvents()} — the
 * same dedup-by-signature path the other two couriers use.
 */
class LogesTechsWebhookController extends Controller
{
    public function handleTracking(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            Log::channel('logestechs_webhooks')->warning('LogesTechs webhook: empty or non-JSON body');

            return $this->failure(400, 'Invalid payload');
        }

        if (! $this->verifyCredentials($payload, $request->ip())) {
            return $this->failure(401, 'Invalid webhook credentials');
        }

        $barcode = $this->stringOrNull($payload['barcode'] ?? null);
        $supplierInvoice = $this->stringOrNull($payload['supplierInvoice'] ?? null);
        $rawStatus = $this->stringOrNull($payload['newStatus'] ?? $payload['status'] ?? null);

        if ($barcode === null && $supplierInvoice === null) {
            Log::channel('logestechs_webhooks')->warning('LogesTechs webhook missing barcode/supplierInvoice', [
                'payload' => $this->redact($payload),
            ]);

            return $this->failure(400, 'Missing barcode/supplierInvoice');
        }

        if ($rawStatus === null) {
            Log::channel('logestechs_webhooks')->warning('LogesTechs webhook carried no status', [
                'barcode' => $barcode,
                'payload' => $this->redact($payload),
            ]);

            return $this->failure(400, 'Missing newStatus');
        }

        try {
            $shipment = $this->resolveShipment($barcode, $supplierInvoice);

            if (! $shipment) {
                // Acknowledge so this isn't treated as a delivery failure on
                // their side — but log it, because a push for a package we
                // don't recognise usually means a mismatched supplierInvoice
                // rather than someone else's parcel.
                Log::channel('logestechs_webhooks')->warning('LogesTechs webhook for unknown shipment', [
                    'barcode' => $barcode,
                    'supplier_invoice' => $supplierInvoice,
                    'status' => $rawStatus,
                ]);

                return $this->success();
            }

            $event = $this->buildTrackingEvent($payload, $rawStatus);

            DB::transaction(function () use ($shipment, $event): void {
                $shipment->addTrackingEvents([$event]);

                // Pushes can still arrive out of order (LogesTechs sends one
                // per status change with no ordering guarantee), so the coarse
                // status only moves forward; the raw courier text is recorded
                // either way.
                [$appliedStatus, $extra] = $shipment->resolveTrackingStatus($event->status);

                $shipment->update([
                    'status' => $appliedStatus->value,
                    'courier_status' => $event->rawStatus,
                    'courier_status_description' => $event->description,
                    'tracking_history' => $shipment->tracking_history,
                    ...$extra,
                ]);

                match ($event->status) {
                    ShipmentStatus::DELIVERED => $shipment->markDelivered(),
                    ShipmentStatus::RETURNED => $shipment->markReturned($event->description),
                    ShipmentStatus::CANCELLED => $shipment->markCancelled($event->description),
                    ShipmentStatus::EXCEPTION => $this->escalateOnce($shipment, $event->description),
                    default => null,
                };
            });

            Log::channel('logestechs_webhooks')->info('LogesTechs webhook processed', [
                'tracking' => $shipment->tracking_number,
                'raw_status' => $event->rawStatus,
                'status' => $event->status->value,
            ]);
        } catch (Throwable $e) {
            // No retry is coming — capture everything needed to replay this by
            // hand or confirm it via Get Package Status.
            Log::channel('logestechs_webhooks')->error('LogesTechs webhook failed — push will NOT be retried', [
                'barcode' => $barcode,
                'supplier_invoice' => $supplierInvoice,
                'status' => $rawStatus,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $this->redact($payload),
            ]);

            return $this->failure(500, 'Internal error');
        }

        return $this->success();
    }

    /**
     * Build a single tracking event from the push payload.
     *
     * LogesTechs sends one status per call (no `locus`-style array), so unlike
     * the J&T and iMile handlers there is nothing to iterate.
     */
    private function buildTrackingEvent(array $payload, string $rawStatus): TrackingEvent
    {
        $driver = new LogesTechsDriver();

        $description = $this->stringOrNull($payload['notes'] ?? null) ?: $driver->statusLabel($rawStatus);

        // `postponedDeliveryDate` only rides along with POSTPONED_DELIVERY and
        // has no column of its own; folding it into the description keeps the
        // rescheduled date visible in the tracking timeline without a schema
        // change (tracking_history stores the raw payload shape anyway).
        $postponedDate = $this->stringOrNull($payload['postponedDeliveryDate'] ?? $payload['postponedDate'] ?? null);
        if ($postponedDate !== null) {
            $description .= ' (rescheduled for ' . $postponedDate . ')';
        }

        // Proof-of-delivery photos on a successful drop, failure evidence
        // otherwise — mirroring how the J&T handler splits signature vs.
        // problem images across the same two TrackingEvent fields.
        $attachment = $this->firstAttachment($payload['attachmentUrls'] ?? null);
        $isFailure = $driver->normalizeStatus($rawStatus) !== ShipmentStatus::DELIVERED;

        return new TrackingEvent(
            status: $driver->normalizeStatus($rawStatus),
            description: $description,
            location: null,
            timestamp: $driver->normalizeTimestamp($payload['time'] ?? null),
            rawStatus: $rawStatus,
            staffName: $this->stringOrNull($payload['driverName'] ?? null),
            staffContact: $this->stringOrNull($payload['driverPhone'] ?? null),
            signaturePicUrl: $isFailure ? null : $attachment,
            problemPicUrl: $isFailure ? $attachment : null,
        );
    }

    private function firstAttachment(mixed $attachments): ?string
    {
        if (! is_array($attachments)) {
            return null;
        }

        foreach ($attachments as $attachment) {
            $url = $this->stringOrNull($attachment);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Resolve by barcode first (LogesTechs' own identifier, always present on a
     * real push), then by the order reference we sent on creation.
     */
    private function resolveShipment(?string $barcode, ?string $supplierInvoice): ?Shipment
    {
        if ($barcode !== null) {
            $shipment = Shipment::where('tracking_number', $barcode)->first();

            if ($shipment) {
                return $shipment;
            }
        }

        if ($supplierInvoice !== null) {
            return Shipment::where('txlogistic_id', $supplierInvoice)
                ->where('courier', 'logestechs')
                ->first();
        }

        return null;
    }

    private function escalateOnce(Shipment $shipment, string $note): void
    {
        // Notify admins once per shipment so repeated abnormal scans don't spam
        // them — same rule as the J&T and iMile handlers.
        if ($shipment->exception_escalated_at) {
            $shipment->update(['status' => ShipmentStatus::EXCEPTION->value]);

            return;
        }

        $shipment->escalateException($note);
    }

    /**
     * LogesTechs offers no signature, HMAC or documented source IP range. What
     * it does offer is a username/password pair the account holder sets in
     * their portal, echoed back on every push — so that pair is the
     * authenticity check. Weaker than a signature (it's a static secret in the
     * body, replayable by anyone who ever sees one payload), which is exactly
     * why it must be a strong, unique value in production and never the
     * "test"/"test" default from their sample.
     */
    protected function verifyCredentials(array $payload, ?string $ip = null): bool
    {
        $expectedUsername = (string) (ConnectorSetting::getForConnector('logestechs', 'webhook_username')
            ?: config('services.logestechs.webhook_username'));
        $expectedPassword = (string) (ConnectorSetting::getForConnector('logestechs', 'webhook_password')
            ?: config('services.logestechs.webhook_password'));

        // Refuse to run unauthenticated. Without this the endpoint would accept
        // any caller able to guess the URL, and a forged DELIVERED push would
        // silently mark a real order fulfilled.
        if ($expectedUsername === '' || $expectedPassword === '') {
            Log::channel('logestechs_webhooks')->error(
                'LogesTechs webhook rejected: no webhook_username/webhook_password configured. '
                . 'Set them in Apps → LogesTechs Settings to match the values in LogesTechs\' webhook panel.',
                ['ip' => $ip]
            );

            return false;
        }

        $username = (string) ($payload['webhookUsername'] ?? '');
        $password = (string) ($payload['webhookPassword'] ?? '');

        if (! hash_equals($expectedUsername, $username) || ! hash_equals($expectedPassword, $password)) {
            Log::channel('logestechs_webhooks')->warning('LogesTechs webhook: credential mismatch', [
                'received_username' => $username,
                'ip' => $ip,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Strip the webhook password before anything reaches the log — it travels
     * in plaintext in every push body and is the only thing standing between
     * this endpoint and a forged status update.
     */
    private function redact(array $payload): array
    {
        if (array_key_exists('webhookPassword', $payload)) {
            $payload['webhookPassword'] = '[redacted]';
        }

        return $payload;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * LogesTechs asks for "only status 200" and reads nothing from the body.
     */
    private function success(): JsonResponse
    {
        return response()->json(['status' => 'ok'], 200);
    }

    private function failure(int $httpStatus, string $message): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $httpStatus);
    }
}
