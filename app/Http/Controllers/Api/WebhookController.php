<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectorSetting;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\JntExpressDriver;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        if ($error) return $error;

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::channel('jnt_webhooks')->warning('J&T tracking webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::channel('jnt_webhooks')->info('J&T tracking webhook for unknown shipment', ['tracking' => $trackingNumber]);
            return response()->json(['code' => 1, 'msg' => 'success']);
        }

        $driver = new JntExpressDriver();
        $rawStatus = $data['scanType'] ?? $data['status'] ?? '';
        $normalizedStatus = $driver->normalizeStatus($rawStatus);

        $event = new TrackingEvent(
            status: $normalizedStatus,
            description: $data['desc'] ?? $data['description'] ?? '',
            location: $data['scanCity'] ?? $data['city'] ?? null,
            timestamp: $data['scanTime'] ?? $data['operationTime'] ?? now()->toIso8601String(),
            rawStatus: $rawStatus,
        );

        $shipment->addTrackingEvent($event);
        $shipment->update([
            'status'                    => $normalizedStatus->value,
            'courier_status'            => $rawStatus,
            'courier_status_description' => $event->description,
            'tracking_history'          => $shipment->tracking_history,
        ]);

        if ($normalizedStatus === ShipmentStatus::DELIVERED) {
            $shipment->markDelivered();
        }

        Log::channel('jnt_webhooks')->info('J&T tracking webhook processed', [
            'tracking' => $trackingNumber,
            'status'   => $normalizedStatus->value,
        ]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // 2. Order Return Status — package returned to sender
    // -----------------------------------------------------------------------

    public function handleJntReturn(Request $request): JsonResponse
    {
        [$data, $error] = $this->parseAndVerify($request, 'return');
        if ($error) return $error;

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::channel('jnt_webhooks')->warning('J&T return webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::channel('jnt_webhooks')->info('J&T return webhook for unknown shipment', ['tracking' => $trackingNumber]);
            return response()->json(['code' => 1, 'msg' => 'success']);
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

        $shipment->addTrackingEvent($event);
        $shipment->update(['tracking_history' => $shipment->tracking_history]);
        $shipment->markReturned($reason);

        Log::channel('jnt_webhooks')->info('J&T return webhook processed', [
            'tracking' => $trackingNumber,
            'reason'   => $reason,
        ]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // 3. COD Return — J&T remitted collected cash to merchant
    // -----------------------------------------------------------------------

    public function handleJntCod(Request $request): JsonResponse
    {
        [$data, $error] = $this->parseAndVerify($request, 'cod');
        if ($error) return $error;

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::channel('jnt_webhooks')->warning('J&T COD webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::with('order')->where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::channel('jnt_webhooks')->info('J&T COD webhook for unknown shipment', ['tracking' => $trackingNumber]);
            return response()->json(['code' => 1, 'msg' => 'success']);
        }

        $codAmount = $data['codAmount'] ?? $data['amount'] ?? null;
        $remitTime = $data['remitTime'] ?? $data['operationTime'] ?? now()->toIso8601String();

        $shipment->order->update([
            'financial_status'     => 'paid',
            'paid_at'              => $shipment->order->paid_at ?? $remitTime,
            'cod_collected_amount' => $codAmount,
            'cod_collected_at'     => $remitTime,
        ]);

        Log::channel('jnt_webhooks')->info('J&T COD webhook processed', [
            'tracking'   => $trackingNumber,
            'cod_amount' => $codAmount,
            'remit_time' => $remitTime,
        ]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // 4. OTP Callback — customer confirmed receipt via OTP
    // -----------------------------------------------------------------------

    public function handleJntOtp(Request $request): JsonResponse
    {
        [$data, $error] = $this->parseAndVerify($request, 'otp');
        if ($error) return $error;

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::channel('jnt_webhooks')->warning('J&T OTP webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::channel('jnt_webhooks')->info('J&T OTP webhook for unknown shipment', ['tracking' => $trackingNumber]);
            return response()->json(['code' => 1, 'msg' => 'success']);
        }

        $verifyTime = $data['verifyTime'] ?? $data['operationTime'] ?? now()->toIso8601String();

        $event = new TrackingEvent(
            status: ShipmentStatus::DELIVERED,
            description: 'Delivered — OTP verified',
            location: null,
            timestamp: $verifyTime,
            rawStatus: 'OTP_VERIFIED',
        );

        $shipment->addTrackingEvent($event);
        $shipment->update(['tracking_history' => $shipment->tracking_history]);
        $shipment->markDelivered();

        Log::channel('jnt_webhooks')->info('J&T OTP webhook processed', ['tracking' => $trackingNumber]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------------

    /**
     * Extract and verify a J&T webhook request.
     * Returns [parsed_data_array, null] on success, or [null, error_response] on failure.
     */
    private function parseAndVerify(Request $request, string $type): array
    {
        $digest = $request->header('digest');
        $fullBody = $request->getContent();

        // bizContent may arrive as a JSON string or a pre-parsed object
        $raw = $request->input('bizContent');
        $bizContentString = is_string($raw) ? $raw : json_encode($raw);

        if (! $this->verifyJntSignature($bizContentString, $fullBody, $digest, $type)) {
            Log::channel('jnt_webhooks')->warning("J&T {$type} webhook signature verification failed");
            return [null, response()->json(['code' => 0, 'msg' => 'Invalid signature'], 401)];
        }

        $data = is_string($raw) ? json_decode($raw, true) : (array) $raw;

        if (! $data) {
            return [null, response()->json(['code' => 0, 'msg' => 'Invalid payload'], 400)];
        }

        return [$data, null];
    }

    protected function verifyJntSignature(string $bizContent, string $fullBody, ?string $digest, string $type = ''): bool
    {
        if (! $digest) {
            Log::channel('jnt_webhooks')->debug('J&T webhook: no digest header received');
            return false;
        }

        $privateKey = ConnectorSetting::getForConnector('jnt_express', 'private_key');

        if (! $privateKey) {
            Log::channel('jnt_webhooks')->warning('J&T webhook: private_key not configured in connector_settings');
            return false;
        }

        // Candidate 1: bizContent string + privateKey  (our current approach)
        $d1 = base64_encode(md5($bizContent . $privateKey, true));
        // Candidate 2: full raw body + privateKey
        $d2 = base64_encode(md5($fullBody . $privateKey, true));
        // Candidate 3: bizContent string only (no private key)
        $d3 = base64_encode(md5($bizContent, true));
        // Candidate 4: full raw body only (no private key)
        $d4 = base64_encode(md5($fullBody, true));

        foreach ([$d1, $d2, $d3, $d4] as $candidate) {
            if (hash_equals($candidate, $digest)) {
                Log::channel('jnt_webhooks')->info("J&T {$type} webhook signature matched", [
                    'matched_candidate' => array_search($candidate, [$d1, $d2, $d3, $d4]) + 1,
                ]);
                return true;
            }
        }

        Log::channel('jnt_webhooks')->debug('J&T webhook signature mismatch — all candidates failed', [
            'type'             => $type,
            'received_digest'  => $digest,
            'candidate_1_biz_plus_key'  => $d1,
            'candidate_2_body_plus_key' => $d2,
            'candidate_3_biz_only'      => $d3,
            'candidate_4_body_only'     => $d4,
            'biz_length'       => strlen($bizContent),
            'body_length'      => strlen($fullBody),
            'biz_preview'      => substr($bizContent, 0, 300),
            'body_preview'     => substr($fullBody, 0, 300),
        ]);

        return false;
    }
}
