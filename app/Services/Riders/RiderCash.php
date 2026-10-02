<?php

namespace App\Services\Riders;

use App\Models\RiderPayment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\RiderAction;

/**
 * What each rider owes KSA Drop: the COD they collected in cash, minus what
 * they have handed in. Worked out from the records every time, never stored,
 * so it can't drift from them.
 *
 * Only cash is in the rider's pocket. COD paid by card or transfer reaches
 * KSA Drop directly; it's reported as `direct` and never owed.
 */
class RiderCash
{
    /** shipment_events.payment_method the rider has to hand in. */
    public const OWED_METHOD = 'cash';

    /**
     * A balance above zero is what the rider still owes; below zero, they
     * handed in more than they collected.
     *
     * @param  array<int>  $riderIds
     * @return array<int, array{collected: float, direct: float, paid: float, balance: float}>
     */
    public static function forRiders(array $riderIds): array
    {
        $cash = [];
        foreach ($riderIds as $id) {
            $cash[$id] = ['collected' => 0.0, 'direct' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
        }

        if ($riderIds === []) {
            return $cash;
        }

        // Every delivery the rider ever collected for, answered from
        // shipment_events_rider_cash_index without reading the rows.
        $collected = ShipmentEvent::whereIn('rider_id', $riderIds)
            ->where('action', RiderAction::DELIVERED->value)
            ->whereNotNull('payment_method')
            ->selectRaw('rider_id, payment_method, COALESCE(SUM(cod_amount), 0) as total')
            ->groupBy('rider_id', 'payment_method')
            ->get();

        foreach ($collected as $row) {
            $key = $row->payment_method === self::OWED_METHOD ? 'collected' : 'direct';
            $cash[$row->rider_id][$key] += (float) $row->total;
        }

        $paid = RiderPayment::counted()
            ->handedIn()
            ->whereIn('rider_id', $riderIds)
            ->selectRaw('rider_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('rider_id')
            ->pluck('total', 'rider_id');

        foreach ($paid as $riderId => $total) {
            $cash[$riderId]['paid'] = (float) $total;
        }

        return array_map(fn (array $row) => [
            'collected' => round($row['collected'], 2),
            'direct' => round($row['direct'], 2),
            'paid' => round($row['paid'], 2),
            'balance' => round($row['collected'] - $row['paid'], 2),
        ], $cash);
    }

    /**
     * @return array{collected: float, direct: float, paid: float, balance: float}
     */
    public static function forRider(int $riderId): array
    {
        return self::forRiders([$riderId])[$riderId];
    }
}
