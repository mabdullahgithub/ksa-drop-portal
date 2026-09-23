<?php

namespace App\Services\Shipping\Drivers;

use App\Models\Shipment;
use App\Services\Shipping\Contracts\CourierDriver;
use App\Services\Shipping\DTOs\CancelResult;
use App\Services\Shipping\DTOs\ShipmentData;
use App\Services\Shipping\DTOs\ShipmentResult;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\DTOs\TrackingResult;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Support\Facades\Cache;

/**
 * KSA Drop's own in-house courier. There is no external API: the waybill
 * number is minted here and everything else lives on the shipment row.
 */
class KsaDropExpressDriver implements CourierDriver
{
    public const KEY = 'ksadrop_express';

    public const TRACKING_PREFIX = 'KSD';

    public function createShipment(ShipmentData $data): ShipmentResult
    {
        // A modify (operate_type 2) keeps the waybill the parcel was booked
        // under — it's already printed on the label.
        if ($data->operateType === 2) {
            $existing = Shipment::where('courier', self::KEY)
                ->where('txlogistic_id', $data->txlogisticId)
                ->value('tracking_number');

            if ($existing) {
                return ShipmentResult::success($existing, null, $data->txlogisticId, $this->bookingRecord($existing, $data));
            }
        }

        $trackingNumber = $this->generateTrackingNumber();

        return ShipmentResult::success($trackingNumber, null, $data->txlogisticId, $this->bookingRecord($trackingNumber, $data));
    }

    /**
     * Stored as shipments.api_response. With no courier holding the booking,
     * this is the only record of the receiver details the admin confirmed in
     * the create dialog — the waybill prints from it rather than the order.
     */
    protected function bookingRecord(string $trackingNumber, ShipmentData $data): array
    {
        return [
            'courier' => self::KEY,
            'tracking_number' => $trackingNumber,
            'receiver' => $data->receiver,
            'remark' => $data->remark,
        ];
    }

    /**
     * KSD + YYMM + a 6-digit sequence that restarts monthly, e.g. KSD2609000001.
     *
     * The lock only serialises generation, not the caller's insert — the
     * unique (courier, tracking_number) index is the real guard against a
     * duplicate slipping through.
     */
    public function generateTrackingNumber(): string
    {
        return Cache::lock('ksadrop-express-tracking', 10)->block(5, function () {
            $prefix = self::TRACKING_PREFIX . now()->format('ym');

            $latest = Shipment::where('courier', self::KEY)
                ->where('tracking_number', 'like', $prefix . '%')
                ->orderByDesc('tracking_number')
                ->value('tracking_number');

            $next = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

            return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Nothing to ask — hand back what's stored. SyncShipmentTracking and the
     * on-demand refresh overwrite tracking_history with whatever a driver
     * returns, so returning the stored state keeps both as no-ops.
     */
    public function trackShipment(string $trackingNumber): TrackingResult
    {
        $shipment = Shipment::where('courier', self::KEY)
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (! $shipment) {
            return TrackingResult::failure("Shipment {$trackingNumber} not found.");
        }

        $events = array_map(
            fn (array $event) => TrackingEvent::fromArray($event),
            $shipment->tracking_history ?? []
        );

        return TrackingResult::success($shipment->status_enum, $events, []);
    }

    public function cancelShipment(string $trackingNumber, string $reason): CancelResult
    {
        return CancelResult::success();
    }

    public function testConnection(): bool
    {
        return true;
    }

    public function getDriverName(): string
    {
        return self::KEY;
    }

    public function normalizeStatus(string $rawStatus): ShipmentStatus
    {
        return ShipmentStatus::tryFrom($rawStatus) ?? ShipmentStatus::PENDING;
    }
}
