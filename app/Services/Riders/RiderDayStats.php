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
 *
 * A parcel is counted once, by where it stands now: one that failed this
 * morning and was delivered this afternoon is delivered, not failed.
 */
class RiderDayStats
{
    /**
     * held: every parcel in the rider's hands, of which `failed` are waiting
     * for another try and `to_return` have to be handed back to the hub.
     * delivered, cod_collected (any payment method) and cash_collected (cash
     * only, what the rider has to hand in) are today's.
     *
     * @param  array<int>  $riderIds
     * @return array<int, array{held: int, delivered: int, failed: int, to_return: int, cod_collected: float, cash_collected: float}>
     */
    public static function forRiders(array $riderIds): array
    {
        $stats = [];
        foreach ($riderIds as $id) {
            $stats[$id] = ['held' => 0, 'delivered' => 0, 'failed' => 0, 'to_return' => 0, 'cod_collected' => 0.0, 'cash_collected' => 0.0];
        }

        if ($riderIds === []) {
            return $stats;
        }

        $held = Shipment::whereIn('rider_id', $riderIds)
            ->inRiderHands()
            ->selectRaw('rider_id, status, COUNT(*) as total')
            ->groupBy('rider_id', 'status')
            ->get();

        foreach ($held as $row) {
            $stats[$row->rider_id]['held'] += (int) $row->total;

            if ($row->status === ShipmentStatus::ATTEMPT_FAIL->value) {
                $stats[$row->rider_id]['failed'] += (int) $row->total;
            } elseif ($row->status !== ShipmentStatus::OUT_FOR_DELIVERY->value) {
                $stats[$row->rider_id]['to_return'] += (int) $row->total;
            }
        }

        $delivered = ShipmentEvent::whereIn('rider_id', $riderIds)
            ->where('occurred_at', '>=', self::startOfToday())
            ->where('action', RiderAction::DELIVERED->value)
            ->selectRaw('rider_id, payment_method, COUNT(*) as total, COALESCE(SUM(cod_amount), 0) as cod')
            ->groupBy('rider_id', 'payment_method')
            ->get();

        foreach ($delivered as $row) {
            $stats[$row->rider_id]['delivered'] += (int) $row->total;
            $stats[$row->rider_id]['cod_collected'] += (float) $row->cod;

            if ($row->payment_method === RiderCash::OWED_METHOD) {
                $stats[$row->rider_id]['cash_collected'] += (float) $row->cod;
            }
        }

        foreach ($stats as $id => $row) {
            $stats[$id]['cod_collected'] = round($row['cod_collected'], 2);
            $stats[$id]['cash_collected'] = round($row['cash_collected'], 2);
        }

        return $stats;
    }

    public static function startOfToday(): Carbon
    {
        return now(config('app.business_timezone', 'Asia/Riyadh'))->startOfDay()->utc();
    }
}
