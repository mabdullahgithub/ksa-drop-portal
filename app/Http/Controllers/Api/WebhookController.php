<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectorSetting;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\JntExpressDriver;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\JntErrorCode;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    // -----------------------------------------------------------------------
    // 1. Logistics Tracking Return — scan events (pickup, in-transit, OFD, delivered)
    // -----------------------------------------------------------------------

    public function handleJntTracking(Request $request): JsonResponse
    {
        return $this->handleJntExpress($request);
    }

    public function handleJntExpress(Request $request): JsonResponse
    {
        [$data, $error] = $this->parseAndVerify($request, 'tracking');
        if ($error) {
            return $error;
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;
        $txlogisticId   = $data['txlogisticId'] ?? null;

        if (! $trackingNumber && ! $txlogisticId) {
            Log::channel('jnt_webhooks')->warning('J&T tracking webhook missing billCode/txlogisticId', ['data' => $data]);
            return $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Missing billCode');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                Log::channel('jnt_webhooks')->info('J&T tracking webhook for unknown shipment', [
                    'tracking'      => $trackingNumber,
                    'txlogistic_id' => $txlogisticId,
                ]);
                // Acknowledge so J&T stops retrying a waybill we don't own.
                return $this->success();
            }

            $events = $this->extractTrackingEvents($data);

            if ($events === []) {
                Log::channel('jnt_webhooks')->info('J&T tracking webhook carried no scan details', [
                    'tracking' => $shipment->tracking_number,
                ]);
                return $this->success();
            }

            $latest = $this->latestEvent($events);

            DB::transaction(function () use ($shipment, $events, $latest): void {
                $shipment->addTrackingEvents($events);

                // A push may arrive out of order (retry, race, batching) —
                // only move the coarse status forward; the raw courier text
                // below is always recorded regardless.
                [$appliedStatus, $extra] = $shipment->resolveTrackingStatus($latest->status);

                $shipment->update([
                    'status'                     => $appliedStatus->value,
                    'courier_status'             => $latest->rawStatus,
                    'courier_status_description' => $latest->description,
                    'tracking_history'           => $shipment->tracking_history,
                    ...$extra,
                ]);

                // Capture an OTP delivered on a sign scan.
                $otpEvent = $this->firstEventWithOtp($events);
                if ($otpEvent && ! $shipment->otp_verified) {
                    $shipment->markOtpVerified();
                }

                // Fire terminal-state side-effects based on the most recent scan.
                match ($latest->status) {
                    ShipmentStatus::DELIVERED => $shipment->markDelivered(),
                    ShipmentStatus::RETURNED  => $shipment->markReturned($latest->description),
                    ShipmentStatus::CANCELLED => $shipment->markCancelled($latest->description),
                    ShipmentStatus::EXCEPTION => $this->escalateOnce($shipment, $latest->description),
                    default                   => null,
                };
            });

            Log::channel('jnt_webhooks')->info('J&T tracking webhook processed', [
                'tracking'    => $shipment->tracking_number,
                'status'      => $latest->status->value,
                'scan_events' => count($events),
            ]);
        } catch (Throwable $e) {
            Log::channel('jnt_webhooks')->error('J&T tracking webhook failed', [
                'tracking'  => $trackingNumber,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'payload'   => $data,
            ]);

            return $this->failure(JntErrorCode::INTERNAL_CALL_EXCEPTION, 'Internal error');
        }

        return $this->success();
    }

    // -----------------------------------------------------------------------
    // 2. Order Return Status — package returned to sender
    // -----------------------------------------------------------------------

    public function handleJntReturn(Request $request): JsonResponse
    {
        [$data, $error] = $this->parseAndVerify($request, 'return');
        if ($error) {
            return $error;
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? $data['waybillNo'] ?? null;
        $txlogisticId   = $data['txlogisticId'] ?? null;

        if (! $trackingNumber && ! $txlogisticId) {
            Log::channel('jnt_webhooks')->warning('J&T return webhook missing tracking number', ['data' => $data]);
            return $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Missing billCode');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                Log::channel('jnt_webhooks')->info('J&T return webhook for unknown shipment', ['tracking' => $trackingNumber]);
                return $this->success();
            }

            $reason = $data['returnReason'] ?? $data['reason'] ?? '';
            $returnTime = $data['returnTime'] ?? $data['operationTime'] ?? now()->toIso8601String();

            $event = new TrackingEvent(
                status: ShipmentStatus::RETURNED,
                description: $reason ?: 'Package returned to sender',
                location: $data['city'] ?? null,
                timestamp: $returnTime,
                rawStatus: 'RETURNED',
            );

            DB::transaction(function () use ($shipment, $event, $reason): void {
                $shipment->addTrackingEvent($event);
                $shipment->update(['tracking_history' => $shipment->tracking_history]);
                $shipment->markReturned($reason);
            });

            Log::channel('jnt_webhooks')->info('J&T return webhook processed', [
                'tracking' => $trackingNumber,
                'reason'   => $reason,
            ]);
        } catch (Throwable $e) {
            Log::channel('jnt_webhooks')->error('J&T return webhook failed', [
                'tracking'  => $trackingNumber,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'payload'   => $data,
            ]);

            return $this->failure(JntErrorCode::INTERNAL_CALL_EXCEPTION, 'Internal error');
        }

        return $this->success();
    }

    // -----------------------------------------------------------------------
    // 3. COD Return — J&T remitted collected cash to merchant
    // -----------------------------------------------------------------------

    public function handleJntCod(Request $request): JsonResponse
    {
        [$data, $error] = $this->parseAndVerify($request, 'cod');
        if ($error) {
            return $error;
        }

        // COD remittance is a batch: wayNos is an array of tracking numbers.
        // Fallback to single-waybill fields for forward compatibility.
        $wayNos = $data['wayNos'] ?? null;
        if (! $wayNos) {
            $single = $data['billCode'] ?? $data['mailNo'] ?? $data['waybillNo'] ?? null;
            $wayNos = $single ? [$single] : [];
        }

        if (empty($wayNos)) {
            Log::channel('jnt_webhooks')->warning('J&T COD webhook missing waybill numbers', ['data' => $data]);
            return $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Missing waybill numbers');
        }

        $totalAmount = $data['payCustomerCollectionAmount'] ?? $data['codAmount'] ?? null;
        $remitTime   = $data['rebateTime'] ?? $data['remitTime'] ?? $data['operationTime'] ?? now()->toIso8601String();
        $batchId     = $data['billSerialNum'] ?? null;

        $updated = 0;
        $failed  = 0;

        foreach ($wayNos as $trackingNumber) {
            try {
                $shipment = Shipment::with('order')->where('tracking_number', $trackingNumber)->first();

                if (! $shipment) {
                    Log::channel('jnt_webhooks')->info('J&T COD webhook: unknown shipment in batch', ['tracking' => $trackingNumber]);
                    continue;
                }

                if (! $shipment->order) {
                    Log::channel('jnt_webhooks')->warning('J&T COD webhook: shipment has no associated order (possibly deleted)', ['tracking' => $trackingNumber]);
                    continue;
                }

                $shipment->order->update([
                    'financial_status'     => 'paid',
                    'paid_at'              => $shipment->order->paid_at ?? $remitTime,
                    'cod_collected_amount' => $totalAmount,
                    'cod_collected_at'     => $remitTime,
                ]);

                $updated++;
            } catch (Throwable $e) {
                $failed++;
                Log::channel('jnt_webhooks')->error('J&T COD webhook: failed to update order', [
                    'tracking'  => $trackingNumber,
                    'batch_id'  => $batchId,
                    'error'     => $e->getMessage(),
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]);
            }
        }

        Log::channel('jnt_webhooks')->info('J&T COD webhook processed', [
            'batch_id'      => $batchId,
            'total_amount'  => $totalAmount,
            'remit_time'    => $remitTime,
            'waybill_count' => count($wayNos),
            'updated'       => $updated,
            'failed'        => $failed,
        ]);

        if ($failed > 0 && $updated === 0) {
            return $this->failure(JntErrorCode::INTERNAL_CALL_EXCEPTION, 'All updates failed');
        }

        return $this->success();
    }

    // -----------------------------------------------------------------------
    // 4. OTP Callback — customer confirmed receipt via OTP
    // -----------------------------------------------------------------------

    public function handleJntOtp(Request $request): JsonResponse
    {
        [$data, $error] = $this->parseAndVerify($request, 'otp');
        if ($error) {
            return $error;
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;
        $txlogisticId   = $data['txlogisticId'] ?? null;

        if (! $trackingNumber && ! $txlogisticId) {
            Log::channel('jnt_webhooks')->warning('J&T OTP webhook missing tracking number', ['data' => $data]);
            return $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Missing billCode');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                Log::channel('jnt_webhooks')->info('J&T OTP webhook for unknown shipment', ['tracking' => $trackingNumber]);
                return $this->success();
            }

            $verifyTime = $data['verifyTime'] ?? $data['operationTime'] ?? now()->toIso8601String();

            $event = new TrackingEvent(
                status: ShipmentStatus::DELIVERED,
                description: 'Delivered — OTP verified',
                location: null,
                timestamp: $verifyTime,
                rawStatus: 'OTP_VERIFIED',
                otp: isset($data['otp']) ? (string) $data['otp'] : null,
            );

            DB::transaction(function () use ($shipment, $event): void {
                $shipment->addTrackingEvent($event);
                $shipment->update(['tracking_history' => $shipment->tracking_history]);
                $shipment->markOtpVerified();
                $shipment->markDelivered();
            });

            Log::channel('jnt_webhooks')->info('J&T OTP webhook processed', ['tracking' => $trackingNumber]);
        } catch (Throwable $e) {
            Log::channel('jnt_webhooks')->error('J&T OTP webhook failed', [
                'tracking'  => $trackingNumber,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'payload'   => $data,
            ]);

            return $this->failure(JntErrorCode::INTERNAL_CALL_EXCEPTION, 'Internal error');
        }

        return $this->success();
    }

    // -----------------------------------------------------------------------
    // Tracking-event helpers
    // -----------------------------------------------------------------------

    /**
     * Build the list of scan events from a tracking-push payload.
     *
     * The spec nests scans inside a `details[]` array (a single push may carry
     * several scans). For forward compatibility we also accept a single scan
     * expressed at the top level of bizContent.
     *
     * @return array<int, TrackingEvent>
     */
    private function extractTrackingEvents(array $data): array
    {
        $details = $data['details'] ?? null;

        if (! is_array($details) || $details === []) {
            $details = (isset($data['scanType']) || isset($data['scanTime']))
                ? [$data]
                : [];
        }

        $driver = new JntExpressDriver();
        $events = [];

        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $events[] = $this->makeTrackingEvent($driver, $detail);
        }

        return $events;
    }

    /**
     * Map a single J&T scan `detail` onto a {@see TrackingEvent}, preserving the
     * rich scan attributes provided in the push.
     */
    private function makeTrackingEvent(JntExpressDriver $driver, array $detail): TrackingEvent
    {
        // Prefer the textual scanType; fall back to the numeric scanTypeCode.
        $scanType = trim((string) ($detail['scanType'] ?? $detail['status'] ?? ''));
        $scanCode = isset($detail['scanTypeCode']) ? trim((string) $detail['scanTypeCode']) : '';
        $rawStatus = $scanType !== '' ? $scanType : $scanCode;

        $location = $detail['scanNetworkCity']
            ?? $detail['scanNetworkArea']
            ?? $detail['scanNetworkProvince']
            ?? $detail['scanCity']
            ?? $detail['city']
            ?? null;

        return new TrackingEvent(
            status: $driver->normalizeStatus($rawStatus !== '' ? $rawStatus : 'unknown'),
            description: (string) ($detail['desc'] ?? $detail['description'] ?? ''),
            location: $location !== null ? (string) $location : null,
            timestamp: (string) ($detail['scanTime'] ?? $detail['operationTime'] ?? now()->toIso8601String()),
            rawStatus: $rawStatus !== '' ? $rawStatus : null,
            scanTypeCode: $scanCode !== '' ? $scanCode : null,
            networkName: isset($detail['scanNetworkName']) ? (string) $detail['scanNetworkName'] : null,
            networkId: isset($detail['scanNetworkId']) ? (string) $detail['scanNetworkId'] : null,
            staffName: isset($detail['staffName']) ? (string) $detail['staffName'] : null,
            staffContact: isset($detail['staffContact']) ? (string) $detail['staffContact'] : null,
            nextStopName: isset($detail['nextStopName']) ? (string) $detail['nextStopName'] : null,
            signaturePicUrl: $detail['electronicSignaturePicUrl'] ?? $detail['sigPicUrl'] ?? null,
            problemPicUrl: $detail['problemPicUrl'] ?? null,
            latitude: isset($detail['latitude']) ? (string) $detail['latitude'] : null,
            longitude: isset($detail['longitude']) ? (string) $detail['longitude'] : null,
            otp: isset($detail['otp']) ? (string) $detail['otp'] : null,
        );
    }

    /**
     * The most recent scan (by scan time) drives the shipment's current status.
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
     * @param  array<int, TrackingEvent>  $events
     */
    private function firstEventWithOtp(array $events): ?TrackingEvent
    {
        foreach ($events as $event) {
            if (! empty($event->otp)) {
                return $event;
            }
        }

        return null;
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
        // Only notify admins once per shipment to avoid alert spam on repeated
        // abnormal scans.
        if ($shipment->exception_escalated_at) {
            $shipment->update(['status' => ShipmentStatus::EXCEPTION->value]);
            return;
        }

        $shipment->escalateException($note);
    }

    // -----------------------------------------------------------------------
    // Request parsing, verification & responses
    // -----------------------------------------------------------------------

    /**
     * Extract and verify a J&T webhook request.
     *
     * Returns [parsed_data_array, null] on success, or [null, error_response]
     * on failure. Error responses carry the documented J&T response codes.
     */
    private function parseAndVerify(Request $request, string $type): array
    {
        try {
            $apiAccount = $request->header('apiAccount');
            $digest     = $request->header('digest');
            $timestamp  = $request->header('timestamp');
            $fullBody   = $request->getContent();

            if (! $apiAccount) {
                return [null, $this->failure(JntErrorCode::API_ACCOUNT_EMPTY)];
            }

            if (! $digest) {
                return [null, $this->failure(JntErrorCode::DIGEST_EMPTY)];
            }

            if (! $timestamp) {
                return [null, $this->failure(JntErrorCode::TIMESTAMP_EMPTY)];
            }

            // bizContent may arrive as a JSON string or a pre-parsed object.
            $raw              = $request->input('bizContent');
            $bizContentString = is_string($raw) ? $raw : json_encode($raw);

            if (! $this->verifyJntSignature($bizContentString, $fullBody, $digest, $apiAccount, $type)) {
                Log::channel('jnt_webhooks')->warning("J&T {$type} webhook signature verification failed", [
                    'ip'             => $request->ip(),
                    'digest_present' => ! empty($digest),
                    'body_length'    => strlen($fullBody),
                ]);
                return [null, $this->failure(JntErrorCode::HEADER_SIGNATURE_INVALID)];
            }

            $data = is_string($raw) ? json_decode($raw, true) : (array) $raw;

            if (! is_array($data) || $data === []) {
                Log::channel('jnt_webhooks')->warning("J&T {$type} webhook empty or invalid bizContent", [
                    'raw_preview' => substr((string) $raw, 0, 200),
                ]);
                return [null, $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Invalid bizContent')];
            }

            return [$data, null];
        } catch (Throwable $e) {
            Log::channel('jnt_webhooks')->error("J&T {$type} webhook parse error", [
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'ip'        => $request->ip(),
            ]);
            return [null, $this->failure(JntErrorCode::SYSTEM_ERROR, 'Parse error')];
        }
    }

    protected function verifyJntSignature(
        string $bizContent,
        string $fullBody,
        ?string $digest,
        ?string $apiAccount = null,
        string $type = '',
    ): bool {
        if (! $digest) {
            Log::channel('jnt_webhooks')->debug('J&T webhook: no digest header received');
            return false;
        }

        $creds           = ConnectorSetting::getAllForConnector('jnt_express');
        $privateKey      = $creds['private_key'] ?? config('services.jnt_express.private_key') ?? '';
        $expectedAccount = $creds['api_account'] ?? config('services.jnt_express.api_account') ?? '';

        if (! $privateKey) {
            Log::channel('jnt_webhooks')->warning('J&T webhook: private_key not configured in connector_settings');
            return false;
        }

        // Sandbox bypass: J&T's console debug test signs with their own internal
        // test key, not your production private key. Enable only during
        // sandbox joint-debugging — never in production.
        if (config('services.jnt.skip_webhook_verification', false)) {
            Log::channel('jnt_webhooks')->info("J&T {$type} webhook: signature verification bypassed (sandbox mode)");
            return true;
        }

        // The pushing account must match our configured J&T account.
        if ($expectedAccount && $apiAccount && ! hash_equals((string) $expectedAccount, (string) $apiAccount)) {
            Log::channel('jnt_webhooks')->warning('J&T webhook: apiAccount mismatch', [
                'received' => $apiAccount,
            ]);
            return false;
        }

        // Documented formula — identical to the outbound signature J&T accepts:
        //   digest = base64( md5( bizContent + privateKey ) )
        $expected = base64_encode(md5($bizContent . $privateKey, true));
        if (hash_equals($expected, $digest)) {
            return true;
        }

        // Safety net: some proxies deliver the raw, still url-encoded bizContent
        // value. Verify against those exact bytes before rejecting.
        if (preg_match('/(?:^|&)bizContent=([^&]*)/', $fullBody, $m) && $m[1] !== '') {
            $expectedRaw = base64_encode(md5($m[1] . $privateKey, true));
            if (hash_equals($expectedRaw, $digest)) {
                return true;
            }
        }

        Log::channel('jnt_webhooks')->debug('J&T webhook signature mismatch', [
            'type'               => $type,
            'private_key_length' => strlen($privateKey),
            'biz_length'         => strlen($bizContent),
            'body_length'        => strlen($fullBody),
        ]);

        return false;
    }

    /**
     * Spec-compliant success response: {"code":"1","msg":"success","data":{}}.
     */
    private function success(array $data = []): JsonResponse
    {
        return response()->json([
            'code' => '1',
            'msg'  => 'success',
            'data' => (object) $data,
        ]);
    }

    /**
     * Spec-compliant failure response carrying a documented J&T code/message.
     *
     * Returned with HTTP 200 so J&T reliably reads the business `code` from the
     * body (its push consumer keys off the JSON code, not the HTTP status).
     */
    private function failure(JntErrorCode $code, ?string $msg = null): JsonResponse
    {
        return response()->json([
            'code' => $code->value,
            'msg'  => $msg ?? $code->message(),
            'data' => (object) [],
        ]);
    }
}
