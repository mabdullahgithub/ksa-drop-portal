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
    public function handleJntExpress(Request $request)
    {
        $body = $request->getContent();
        $digest = $request->header('digest');

        if (! $this->verifyJntSignature($body, $digest)) {
            Log::warning('J&T webhook signature verification failed');
            return response()->json(['code' => 0, 'msg' => 'Invalid signature'], 401);
        }

        $bizContent = $request->input('bizContent');
        $data = is_string($bizContent) ? json_decode($bizContent, true) : $bizContent;

        if (! $data) {
            return response()->json(['code' => 0, 'msg' => 'Invalid payload'], 400);
        }

        $trackingNumber = $data['billCode'] ?? $data['mailNo'] ?? null;

        if (! $trackingNumber) {
            Log::warning('J&T webhook missing tracking number', ['data' => $data]);
            return response()->json(['code' => 0, 'msg' => 'Missing tracking number'], 400);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            Log::info('J&T webhook for unknown shipment', ['tracking' => $trackingNumber]);
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

        Log::info('J&T webhook processed', [
            'tracking' => $trackingNumber,
            'status' => $normalizedStatus->value,
        ]);

        return response()->json(['code' => 1, 'msg' => 'success']);
    }

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
