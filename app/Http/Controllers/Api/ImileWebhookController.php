<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectorSetting;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\ImileDriver;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * iMile tracking-push webhook — see {@see \App\Http\Controllers\Api\WebhookController}
 * for J&T's equivalent. Kept separate because iMile's envelope, signing
 * scheme and response contract are all different from J&T's:
 *
 *  - Envelope is {partnerCode, param, sign, timestamp} (JSON body), not a
 *    signed-header form post.
 *  - The HTTP status code itself carries the outcome (2xx/4xx/5xx), with a
 *    {code, message} JSON body alongside it — J&T instead always returns
 *    HTTP 200 and encodes the outcome only in the body.
 *  - iMile retries a failing delivery up to 3 times, so handling must be
 *    idempotent — reuses {@see \App\Models\Shipment::addTrackingEvents()},
 *    the same dedup-by-signature mechanism the J&T push path relies on.
 *
 * Source: imiles/iMile Webhooks (Tracking Push).md, provided by iMile
 * support in response to our integration request (2026-08-13).
 */
class ImileWebhookController extends Controller
{
    public function handleTracking(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $partnerCode = $payload['partnerCode'] ?? null;
        $timestamp   = $payload['timestamp'] ?? null;
        $sign        = $payload['sign'] ?? null;
        $param       = $payload['param'] ?? null;

        if (! $partnerCode || ! $sign || ! $timestamp || ! is_array($param)) {
            Log::channel('imile_webhooks')->warning('iMile webhook: missing required envelope fields', [
                'has_partner_code' => (bool) $partnerCode,
                'has_sign'         => (bool) $sign,
                'has_timestamp'    => (bool) $timestamp,
                'has_param'        => is_array($param),
            ]);

            return $this->failure(400, '400', 'Missing required fields (partnerCode/param/sign/timestamp)');
        }

        if (! $this->verifySignature((string) $partnerCode, $param, (string) $timestamp, (string) $sign)) {
            Log::channel('imile_webhooks')->warning('iMile webhook: signature verification failed', [
                'partner_code' => $partnerCode,
                'ip'           => $request->ip(),
            ]);

            return $this->failure(401, '401', 'Signature verification failed');
        }

        $trackingNumber = $param['billNo'] ?? null;
        $txlogisticId   = $param['orderNo'] ?? null;

        if (! $trackingNumber && ! $txlogisticId) {
            Log::channel('imile_webhooks')->warning('iMile webhook missing billNo/orderNo', ['param' => $param]);

            return $this->failure(400, '400', 'Missing billNo/orderNo');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                Log::channel('imile_webhooks')->info('iMile webhook for unknown shipment', [
                    'tracking'      => $trackingNumber,
                    'txlogistic_id' => $txlogisticId,
                ]);

                // Acknowledge so iMile stops retrying a waybill we don't own.
                return $this->success();
            }

            $events = $this->extractTrackingEvents($param);

            if ($events === []) {
                Log::channel('imile_webhooks')->info('iMile webhook carried no locus details', [
                    'tracking' => $shipment->tracking_number,
                ]);

                return $this->success();
            }

            $latest = $this->latestEvent($events);

            DB::transaction(function () use ($shipment, $events, $latest): void {
                $shipment->addTrackingEvents($events);
                $shipment->update([
                    'status'                     => $latest->status->value,
                    'courier_status'             => $latest->rawStatus,
                    'courier_status_description' => $latest->description,
                    'tracking_history'           => $shipment->tracking_history,
                ]);

                match ($latest->status) {
                    ShipmentStatus::DELIVERED => $shipment->markDelivered(),
                    ShipmentStatus::RETURNED  => $shipment->markReturned($latest->description),
                    ShipmentStatus::CANCELLED => $shipment->markCancelled($latest->description),
                    ShipmentStatus::EXCEPTION => $this->escalateOnce($shipment, $latest->description),
                    default                   => null,
                };
            });

            Log::channel('imile_webhooks')->info('iMile webhook processed', [
                'tracking'    => $shipment->tracking_number,
                'status'      => $latest->status->value,
                'scan_events' => count($events),
            ]);
        } catch (Throwable $e) {
            Log::channel('imile_webhooks')->error('iMile webhook failed', [
                'tracking'  => $trackingNumber,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'payload'   => $param,
            ]);

            return $this->failure(500, '500', 'Internal error');
        }

        return $this->success();
    }

    /**
     * Build tracking events from the push `param.locus[]` array (reverse-
     * chronological, per the doc). Falls back to a single event synthesised
     * from the top-level latestStatus/latestStatusTime/latestSite/latestLocus
     * fields when `locus` is missing or empty, mirroring how
     * {@see WebhookController::extractTrackingEvents()} handles J&T's
     * single-scan-at-top-level case.
     *
     * @return array<int, TrackingEvent>
     */
    private function extractTrackingEvents(array $param): array
    {
        $locus = $param['locus'] ?? null;

        if (! is_array($locus) || $locus === []) {
            $locus = isset($param['latestStatus']) ? [$param] : [];
        }

        $driver = new ImileDriver();
        $events = [];

        foreach ($locus as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $status = (string) ($entry['latestStatus'] ?? '');

            $events[] = new TrackingEvent(
                status: $driver->normalizeStatus($status),
                description: (string) ($entry['locusDetailed'] ?? $entry['latestLocus'] ?? $status),
                location: isset($entry['latestLocation']) ? (string) $entry['latestLocation'] : (isset($entry['latestSite']) ? (string) $entry['latestSite'] : null),
                timestamp: (string) ($entry['latestStatusTime'] ?? ''),
                rawStatus: $status !== '' ? $status : null,
                latitude: isset($entry['latitude']) ? (string) $entry['latitude'] : null,
                longitude: isset($entry['longitude']) ? (string) $entry['longitude'] : null,
            );
        }

        return $events;
    }

    /**
     * The most recent scan (by timestamp) drives the shipment's current
     * status — same rule as {@see WebhookController::latestEvent()}. iMile's
     * `locus` is documented as already reverse-chronological, but we don't
     * rely on that ordering.
     *
     * @param  array<int, TrackingEvent>  $events
     */
    private function latestEvent(array $events): TrackingEvent
    {
        $latest = $events[0];

        foreach ($events as $event) {
            if (strcmp($event->timestamp, $latest->timestamp) >= 0) {
                $latest = $event;
            }
        }

        return $latest;
    }

    private function resolveShipment(?string $trackingNumber, ?string $txlogisticId): ?Shipment
    {
        if ($trackingNumber) {
            $shipment = Shipment::where('tracking_number', $trackingNumber)->first();
            if ($shipment) {
                return $shipment;
            }
        }

        if ($txlogisticId) {
            return Shipment::where('txlogistic_id', $txlogisticId)->first();
        }

        return null;
    }

    private function escalateOnce(Shipment $shipment, string $note): void
    {
        // Only notify admins once per shipment to avoid alert spam on
        // repeated abnormal scans — same rule as WebhookController's J&T path.
        if ($shipment->exception_escalated_at) {
            $shipment->update(['status' => ShipmentStatus::EXCEPTION->value]);

            return;
        }

        $shipment->escalateException($note);
    }

    /**
     * Verify the inbound push actually came from iMile.
     *
     * iMile's webhook doc ("API Rules" #6) only says the `sign` field uses
     * "SHA-256 or MD5" — it does not specify field order, delimiters, or
     * which of the two applies, and gives no worked example tying the
     * sample payload to its sample `sign` value. We've asked iMile support
     * for the exact formula and for the `secretKey` value to use (it's
     * described as separate from the CustomerID/APIKey pair already used
     * for outbound calls). Until they confirm:
     *
     *   - We cannot cryptographically verify `sign`.
     *   - As a partial mitigation, we reject any push whose `partnerCode`
     *     doesn't match our configured iMile Customer ID — cheap, but it
     *     stops trivially-wrong senders and typos, the same defense-in-depth
     *     check J&T's `apiAccount` verification performs.
     *
     * TODO: once iMile support provides the signing formula + secretKey,
     * replace the "always true" fallback below with a real
     * hash_equals()-based comparison, the same pattern used in
     * WebhookController::verifyJntSignature().
     */
    protected function verifySignature(string $partnerCode, array $param, string $timestamp, string $sign): bool
    {
        $expectedPartnerCode = ConnectorSetting::getForConnector('imile', 'customer_id')
            ?: config('services.imile.customer_id');

        if ($expectedPartnerCode && ! hash_equals((string) $expectedPartnerCode, $partnerCode)) {
            Log::channel('imile_webhooks')->warning('iMile webhook: partnerCode mismatch', [
                'received' => $partnerCode,
            ]);

            return false;
        }

        Log::channel('imile_webhooks')->warning(
            'iMile webhook: signature NOT cryptographically verified — iMile signing spec pending confirmation from support; accepting on partnerCode match only',
            ['partner_code' => $partnerCode],
        );

        return true;
    }

    /**
     * Spec-compliant success response: HTTP 200 + {"code":"200","message":"success"}.
     */
    private function success(): JsonResponse
    {
        return response()->json(['code' => '200', 'message' => 'success'], 200);
    }

    /**
     * Spec-compliant failure response — iMile reads both the HTTP status
     * (2xx/4xx/5xx) and the body `code`/`message`, and retries up to 3 times
     * on non-2xx.
     */
    private function failure(int $httpStatus, string $code, string $message): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $httpStatus);
    }
}
