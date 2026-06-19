<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectorSetting;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\JntExpressDriver;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    // -----------------------------------------------------------------------
    // 1. Logistics Tracking Return — scan events (pickup, in-transit, OFD, delivered)
    // -----------------------------------------------------------------------

    public function handleJntTracking(Request $request)
    {
        return $this->handleJntExpress($request);
    }

    public function handleJntExpress(Request $request)
    {
        $body = $request->getContent();
        $digest = $request->header('digest');

        if (! $this->verifyJntSignature($body, $digest)) {
            Log::warning('J&T tracking webhook signature verification failed');
            return response()->json(['code' => 0, 'msg' => 'Invalid signature'], 401);
        }

        $bizContent = $request->input('bizContent');
        $data = is_string($bizContent) ? json_decode($bizContent, true) : $bizContent;

        if (! $data) {
            return response()->json(['code' => 0, 'msg' => 'Invalid payload'], 400);
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::warning('J&T tracking webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::info('J&T tracking webhook for unknown shipment', ['tracking' => $trackingNumber]);
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
            'status' => $normalizedStatus->value,
            'courier_status' => $rawStatus,
            'courier_status_description' => $event->description,
            'tracking_history' => $shipment->tracking_history,
        ]);

        if ($normalizedStatus === ShipmentStatus::DELIVERED) {
            $shipment->markDelivered();
        }

        Log::info('J&T tracking webhook processed', [
            'tracking' => $trackingNumber,
            'status' => $normalizedStatus->value,
        ]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // 2. Order Return Status — package returned to sender
    // -----------------------------------------------------------------------

    public function handleJntReturn(Request $request)
    {
        $body = $request->getContent();
        $digest = $request->header('digest');

        if (! $this->verifyJntSignature($body, $digest)) {
            Log::warning('J&T return webhook signature verification failed');
            return response()->json(['code' => 0, 'msg' => 'Invalid signature'], 401);
        }

        $bizContent = $request->input('bizContent');
        $data = is_string($bizContent) ? json_decode($bizContent, true) : $bizContent;

        if (! $data) {
            return response()->json(['code' => 0, 'msg' => 'Invalid payload'], 400);
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::warning('J&T return webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::info('J&T return webhook for unknown shipment', ['tracking' => $trackingNumber]);
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

        Log::info('J&T return webhook processed', [
            'tracking' => $trackingNumber,
            'reason'   => $reason,
        ]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // 3. COD Return — J&T remitted collected cash to merchant
    // -----------------------------------------------------------------------

    public function handleJntCod(Request $request)
    {
        $body = $request->getContent();
        $digest = $request->header('digest');

        if (! $this->verifyJntSignature($body, $digest)) {
            Log::warning('J&T COD webhook signature verification failed');
            return response()->json(['code' => 0, 'msg' => 'Invalid signature'], 401);
        }

        $bizContent = $request->input('bizContent');
        $data = is_string($bizContent) ? json_decode($bizContent, true) : $bizContent;

        if (! $data) {
            return response()->json(['code' => 0, 'msg' => 'Invalid payload'], 400);
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::warning('J&T COD webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::with('order')->where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::info('J&T COD webhook for unknown shipment', ['tracking' => $trackingNumber]);
            return response()->json(['code' => 1, 'msg' => 'success']);
        }

        $codAmount = $data['codAmount'] ?? $data['amount'] ?? null;
        $remitTime = $data['remitTime'] ?? $data['operationTime'] ?? now()->toIso8601String();

        $shipment->order->update([
            'financial_status'    => 'paid',
            'paid_at'             => $shipment->order->paid_at ?? $remitTime,
            'cod_collected_amount' => $codAmount,
            'cod_collected_at'    => $remitTime,
        ]);

        Log::info('J&T COD webhook processed', [
            'tracking'   => $trackingNumber,
            'cod_amount' => $codAmount,
            'remit_time' => $remitTime,
        ]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // 4. OTP Callback — customer confirmed receipt via OTP
    // -----------------------------------------------------------------------

    public function handleJntOtp(Request $request)
    {
        $body = $request->getContent();
        $digest = $request->header('digest');

        if (! $this->verifyJntSignature($body, $digest)) {
            Log::warning('J&T OTP webhook signature verification failed');
            return response()->json(['code' => 0, 'msg' => 'Invalid signature'], 401);
        }

        $bizContent = $request->input('bizContent');
        $data = is_string($bizContent) ? json_decode($bizContent, true) : $bizContent;

        if (! $data) {
            return response()->json(['code' => 0, 'msg' => 'Invalid payload'], 400);
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::warning('J&T OTP webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::info('J&T OTP webhook for unknown shipment', ['tracking' => $trackingNumber]);
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

        Log::info('J&T OTP webhook processed', ['tracking' => $trackingNumber]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

    // -----------------------------------------------------------------------
    // Shared signature verification
    // -----------------------------------------------------------------------

    protected function verifyJntSignature(string $body, ?string $digest): bool
    {
        if (! $digest) {
            return false;
        }

        $privateKey = ConnectorSetting::getForConnector('jnt_express', 'private_key');

        if (! $privateKey) {
            return false;
        }

        $expectedDigest = base64_encode(md5($body . $privateKey, true));

        return hash_equals($expectedDigest, $digest);
    }
}
