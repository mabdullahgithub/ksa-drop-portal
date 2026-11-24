<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Logging\AppLog;
use App\Logging\Events\JntEvent;
use App\Models\ConnectorSetting;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\JntExpressDriver;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\JntErrorCode;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            AppLog::emit(JntEvent::WEBHOOK_FIELD_MISSING, tpc: 'tracking');
            return $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Missing billCode');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                AppLog::emit(JntEvent::TRACKING_UNKNOWN_SHIPMENT, trk: $trackingNumber, xid: $txlogisticId);
                // Acknowledge so J&T stops retrying a waybill we don't own.
                return $this->success();
            }

            $events = $this->extractTrackingEvents($data);

            if ($events === []) {
                AppLog::emit(JntEvent::TRACKING_NO_SCANS, shp: $shipment->id);
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

            AppLog::emit(JntEvent::TRACKING_PROCESSED, shp: $shipment->id, sts: $latest->status, cnt: count($events));
        } catch (Throwable $e) {
            AppLog::failure(JntEvent::WEBHOOK_PROCESSING_FAILED, $e, tpc: 'tracking', trk: $trackingNumber);
            AppLog::payload(JntEvent::WEBHOOK_PROCESSING_FAILED, $data);

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
            AppLog::emit(JntEvent::WEBHOOK_FIELD_MISSING, tpc: 'return');
            return $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Missing billCode');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                AppLog::emit(JntEvent::RETURN_UNKNOWN_SHIPMENT, trk: $trackingNumber, xid: $txlogisticId);
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

            AppLog::emit(JntEvent::RETURN_PROCESSED, shp: $shipment->id);
        } catch (Throwable $e) {
            AppLog::failure(JntEvent::WEBHOOK_PROCESSING_FAILED, $e, tpc: 'return', trk: $trackingNumber);
            AppLog::payload(JntEvent::WEBHOOK_PROCESSING_FAILED, $data);

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
            AppLog::emit(JntEvent::WEBHOOK_FIELD_MISSING, tpc: 'cod');
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
                    AppLog::emit(JntEvent::COD_UNKNOWN_SHIPMENT, trk: $trackingNumber, bat: $batchId);
                    continue;
                }

                if (! $shipment->order) {
                    AppLog::emit(JntEvent::COD_SHIPMENT_WITHOUT_ORDER, shp: $shipment->id, bat: $batchId);
                    continue;
                }

                $shipment->order->update([
                    'financial_status'     => 'paid',
                    'paid_at'              => $shipment->order->paid_at ?? $remitTime,
                    'cod_collected_amount' => $totalAmount,
                    'cod_collected_at'     => $remitTime,
                ]);

                AppLog::emit(JntEvent::COD_ORDER_PAID, ord: $shipment->order->id, shp: $shipment->id, bat: $batchId);

                $updated++;
            } catch (Throwable $e) {
                $failed++;
                AppLog::failure(JntEvent::COD_ORDER_UPDATE_FAILED, $e, trk: $trackingNumber, bat: $batchId);
            }
        }

        AppLog::emit(
            JntEvent::COD_PROCESSED,
            cnt: $updated,
            fct: $failed,
            bat: $batchId,
            amt: is_numeric($totalAmount) ? (int) round((float) $totalAmount * 100) : null,
        );

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
            AppLog::emit(JntEvent::WEBHOOK_FIELD_MISSING, tpc: 'otp');
            return $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Missing billCode');
        }

        try {
            $shipment = $this->resolveShipment($trackingNumber, $txlogisticId);

            if (! $shipment) {
                AppLog::emit(JntEvent::OTP_UNKNOWN_SHIPMENT, trk: $trackingNumber, xid: $txlogisticId);
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

            AppLog::emit(JntEvent::OTP_PROCESSED, shp: $shipment->id);
        } catch (Throwable $e) {
            AppLog::failure(JntEvent::WEBHOOK_PROCESSING_FAILED, $e, tpc: 'otp', trk: $trackingNumber);
            AppLog::payload(JntEvent::WEBHOOK_PROCESSING_FAILED, $data);

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
        // Scoped to J&T: a tracking number is only unique per courier, and an
        // order number belongs to whichever courier currently carries the
        // order — a cancelled booking elsewhere must not absorb these scans.
        if ($trackingNumber) {
            $shipment = Shipment::where('courier', 'jnt_express')
                ->where('tracking_number', $trackingNumber)
                ->first();

            if ($shipment) {
                return $shipment;
            }
        }

        if ($txlogisticId) {
            return Shipment::where('courier', 'jnt_express')
                ->where('txlogistic_id', $txlogisticId)
                ->orderByRaw("status = '" . ShipmentStatus::CANCELLED->value . "'")
                ->orderByDesc('id')
                ->first();
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
                AppLog::emit(JntEvent::WEBHOOK_SIGNATURE_INVALID, tpc: $type);
                return [null, $this->failure(JntErrorCode::HEADER_SIGNATURE_INVALID)];
            }

            $data = is_string($raw) ? json_decode($raw, true) : (array) $raw;

            if (! is_array($data) || $data === []) {
                AppLog::emit(JntEvent::WEBHOOK_BIZCONTENT_INVALID, tpc: $type);
                AppLog::payload(JntEvent::WEBHOOK_BIZCONTENT_INVALID, $raw);
                return [null, $this->failure(JntErrorCode::ILLEGAL_PARAMETERS, 'Invalid bizContent')];
            }

            return [$data, null];
        } catch (Throwable $e) {
            AppLog::failure(JntEvent::WEBHOOK_PARSE_FAILED, $e, tpc: $type);
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
            AppLog::emit(JntEvent::WEBHOOK_DIGEST_MISSING);
            return false;
        }

        $creds           = ConnectorSetting::getAllForConnector('jnt_express');
        $privateKey      = $creds['private_key'] ?? config('services.jnt_express.private_key') ?? '';
        $expectedAccount = $creds['api_account'] ?? config('services.jnt_express.api_account') ?? '';

        if (! $privateKey) {
            AppLog::emit(JntEvent::WEBHOOK_PRIVATE_KEY_MISSING);
            return false;
        }

        // Sandbox bypass: J&T's console debug test signs with their own internal
        // test key, not your production private key. Enable only during
        // sandbox joint-debugging — never in production.
        if (config('services.jnt.skip_webhook_verification', false)) {
            AppLog::emit(JntEvent::WEBHOOK_SIGNATURE_BYPASSED, tpc: $type);
            return true;
        }

        // The pushing account must match our configured J&T account.
        if ($expectedAccount && $apiAccount && ! hash_equals((string) $expectedAccount, (string) $apiAccount)) {
            AppLog::emit(JntEvent::WEBHOOK_ACCOUNT_MISMATCH, acc: $apiAccount);
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

        AppLog::emit(JntEvent::WEBHOOK_SIGNATURE_MISMATCH, tpc: $type);

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
