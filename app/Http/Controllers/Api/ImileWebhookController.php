<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Logging\AppLog;
use App\Logging\Events\ImileEvent;
use App\Models\ConnectorSetting;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\ImileDriver;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * iMile tracking-push webhook — see {@see \App\Http\Controllers\Api\WebhookController}
 * for J&T's equivalent. Kept separate because iMile's envelope and response
 * contract are both different from J&T's:
 *
 *  - Envelope is {partnerCode, param, sign, timestamp} (JSON body), not a
 *    signed-header form post.
 *  - The HTTP status code itself carries the outcome (2xx/4xx/5xx), with a
 *    {code, message} JSON body alongside it — J&T instead always returns
 *    HTTP 200 and encodes the outcome only in the body.
 *  - iMile retries a failing delivery up to 3 times, so handling must be
 *    idempotent — reuses {@see \App\Models\Shipment::addTrackingEvents()},
 *    the same dedup-by-signature mechanism the J&T push path relies on.
 *  - Unlike J&T, there is no cryptographic signature to verify: iMile
 *    support confirmed (2026-08-13) that the payload arrives exactly as
 *    documented and requires no encryption/decryption on our end, so the
 *    `sign` field is not used for authentication — see
 *    {@see verifyPartnerCode()} for the check we do perform instead.
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
        $param       = $payload['param'] ?? null;

        if (! $partnerCode || ! $timestamp || ! is_array($param)) {
            AppLog::emit(ImileEvent::WEBHOOK_ENVELOPE_INCOMPLETE);

            return $this->failure(400, '400', 'Missing required fields (partnerCode/param/timestamp)');
        }

        // No cryptographic signature to check (see class docblock) — the
        // partnerCode match is the actual authenticity check for this webhook.
        if (! $this->verifyPartnerCode((string) $partnerCode, $request->ip())) {
            return $this->failure(401, '401', 'partnerCode mismatch');
        }

        $trackingNumber = $param['billNo'] ?? null;
        $txlogisticId   = $param['orderNo'] ?? null;

        if (! $trackingNumber && ! $txlogisticId) {
            AppLog::emit(ImileEvent::WEBHOOK_FIELD_MISSING);
            AppLog::payload(ImileEvent::WEBHOOK_FIELD_MISSING, $param);

            return $this->failure(400, '400', 'Missing billNo/orderNo');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                AppLog::emit(ImileEvent::TRACKING_UNKNOWN_SHIPMENT, trk: $trackingNumber, xid: $txlogisticId);

                // Acknowledge so iMile stops retrying a waybill we don't own.
                return $this->success();
            }

            $events = $this->extractTrackingEvents($param);

            if ($events === []) {
                AppLog::emit(ImileEvent::TRACKING_NO_SCANS, shp: $shipment->id);

                return $this->success();
            }

            $latest = $this->latestEvent($events);

            DB::transaction(function () use ($shipment, $events, $latest): void {
                $shipment->addTrackingEvents($events);

                // A push may arrive out of order (iMile retries a failed
                // delivery up to 3 times) — only move the coarse status
                // forward; the raw courier text is always recorded regardless.
                $previousStatus = $shipment->status_enum;
                [$appliedStatus, $extra] = $shipment->resolveTrackingStatus($latest->status);

                $shipment->update([
                    'status'                     => $appliedStatus->value,
                    'courier_status'             => $latest->rawStatus,
                    'courier_status_description' => $latest->description,
                    'tracking_history'           => $shipment->tracking_history,
                    ...$extra,
                ]);

                // Driven by the status actually applied, not the scan's: a
                // problem scan that arrives after Delivered must neither
                // reopen the shipment nor alert anyone about it.
                match (true) {
                    $appliedStatus === ShipmentStatus::DELIVERED => $shipment->markDelivered(),
                    $appliedStatus === ShipmentStatus::EXCEPTION => $this->escalateOnce($shipment, $latest->description),
                    // Repeat pushes of a return/cancel would otherwise queue
                    // the Shopify fulfillment cancel again each time.
                    $appliedStatus === $previousStatus           => null,
                    $appliedStatus === ShipmentStatus::RETURNED  => $shipment->markReturned($latest->description),
                    $appliedStatus === ShipmentStatus::CANCELLED => $shipment->markCancelled($latest->description),
                    default                                      => null,
                };
            });

            AppLog::emit(ImileEvent::TRACKING_PROCESSED, shp: $shipment->id, sts: $latest->status, cnt: count($events));
        } catch (Throwable $e) {
            AppLog::failure(ImileEvent::WEBHOOK_PROCESSING_FAILED, $e, trk: $trackingNumber);
            AppLog::payload(ImileEvent::WEBHOOK_PROCESSING_FAILED, $param);

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

    /**
     * Both lookups are scoped to iMile: tracking numbers are only unique per
     * courier, and an order number is reused by whichever courier carries the
     * order next — an unscoped match wrote iMile's scans onto a cancelled
     * in-house shipment that happened to share the order number.
     */
    private function resolveShipment(?string $trackingNumber, ?string $txlogisticId): ?Shipment
    {
        if ($trackingNumber) {
            $shipment = Shipment::where('courier', 'imile')
                ->where('tracking_number', $trackingNumber)
                ->first();

            if ($shipment) {
                return $shipment;
            }
        }

        if ($txlogisticId) {
            // A cancelled booking keeps its order number, so prefer a live one.
            return Shipment::where('courier', 'imile')
                ->where('txlogistic_id', $txlogisticId)
                ->orderByRaw("status = '" . ShipmentStatus::CANCELLED->value . "'")
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }

    private function escalateOnce(Shipment $shipment, string $note): void
    {
        // Only notify admins once per shipment to avoid alert spam on
        // repeated abnormal scans — same rule as WebhookController's J&T path.
        if (! $shipment->exception_escalated_at) {
            $shipment->escalateException($note);
        }
    }

    /**
     * Verify the inbound push actually came from iMile.
     *
     * iMile's webhook doc ("API Rules" #6) describes `sign` as a signature
     * generated after encryption — but iMile support confirmed directly
     * (2026-08-13) that for our integration "the payload will be received
     * as shown in the document, there is no need for any encryption or
     * decryption." In other words: no secretKey exchange, no hash formula,
     * and `sign` is not something we're expected to verify.
     *
     * That leaves `partnerCode` matching our configured iMile Customer ID
     * as the actual authenticity check — cheap, but it stops trivially-wrong
     * senders and typos, the same defense-in-depth check J&T's `apiAccount`
     * verification performs.
     */
    protected function verifyPartnerCode(string $partnerCode, ?string $ip = null): bool
    {
        $expectedPartnerCode = ConnectorSetting::getForConnector('imile', 'customer_id')
            ?: config('services.imile.customer_id');

        // iMile stamps webhooks with the partnerCode of the account that
        // booked the shipment, which is not always the CustomerID the API
        // currently signs with (e.g. after the API credentials were rotated,
        // iMile kept pushing the old code). Extra codes can be listed in the
        // `webhook_partner_codes` setting, comma or space separated.
        $accepted = array_filter(array_map('trim', preg_split(
            '/[\s,]+/',
            (string) ConnectorSetting::getForConnector('imile', 'webhook_partner_codes'),
        ) ?: []));

        if ($expectedPartnerCode) {
            $accepted[] = (string) $expectedPartnerCode;
        }

        // Nothing configured at all: keep the original behaviour of letting
        // the push through rather than dropping tracking on a fresh install.
        if ($accepted === []) {
            return true;
        }

        foreach ($accepted as $code) {
            if (hash_equals($code, $partnerCode)) {
                return true;
            }
        }

        AppLog::emit(ImileEvent::WEBHOOK_PARTNER_CODE_MISMATCH, acc: $partnerCode);

        return false;
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
