<?php

namespace App\Services\Riders;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\RiderAction;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Support\Carbon;

/**
 * Per-rider numbers for "today" in the business timezone — the rider app's
 * home screen and the portal's Riders table show the same figures.
 */
class RiderDayStats
{
    /**
     * @param  array<int>  $riderIds
     * @return array<int, array{held: int, delivered: int, failed: int, cod_collected: float}>
     */
    public static function forRiders(array $riderIds): array
    {
        $stats = [];
        foreach ($riderIds as $id) {
            $stats[$id] = ['held' => 0, 'delivered' => 0, 'failed' => 0, 'cod_collected' => 0.0];
        }

        if ($riderIds === []) {
            return $stats;
        }

        $held = Shipment::whereIn('rider_id', $riderIds)
            ->whereIn('status', [ShipmentStatus::OUT_FOR_DELIVERY->value, ShipmentStatus::ATTEMPT_FAIL->value])
            ->selectRaw('rider_id, COUNT(*) as total')
            ->groupBy('rider_id')
            ->pluck('total', 'rider_id');

        $today = ShipmentEvent::whereIn('rider_id', $riderIds)
            ->where('occurred_at', '>=', self::startOfToday())
            ->whereIn('action', [RiderAction::DELIVERED->value, RiderAction::ATTEMPT_FAILED->value])
            ->selectRaw('rider_id, action, COUNT(*) as total, COALESCE(SUM(cod_amount), 0) as cod')
            ->groupBy('rider_id', 'action')
            ->get();

        foreach ($held as $riderId => $total) {
            $stats[$riderId]['held'] = (int) $total;
        }

        foreach ($today as $row) {
            if ($row->action === RiderAction::DELIVERED->value) {
                $stats[$row->rider_id]['delivered'] = (int) $row->total;
                $stats[$row->rider_id]['cod_collected'] = round((float) $row->cod, 2);
            } else {
                $stats[$row->rider_id]['failed'] = (int) $row->total;
            }
        }

        return $stats;
    }

    public static function startOfToday(): Carbon
    {
        return now(config('app.business_timezone', 'Asia/Riyadh'))->startOfDay()->utc();
    }
}
