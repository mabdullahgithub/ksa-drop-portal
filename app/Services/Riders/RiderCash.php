<?php

namespace App\Services\Riders;

use App\Models\RiderPayment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\RiderAction;
use Illuminate\Support\Carbon;

/**
 * What each rider owes KSA Drop: every COD they took from customers —
 * cash, card or transfer — minus what they have handed in. Worked out from
 * the records every time, never stored, so it can't drift from them.
 *
 * `collected` is the cash part; `direct` is the rest, split into `card` and
 * `transfer`. All of it is owed. Staff record each payment against one of
 * the three (rider_payments.cod_method), so `owed` says what is left of each.
 */
class RiderCash
{
    /** shipment_events.payment_method counted as cash in the rider's figures. */
    public const OWED_METHOD = 'cash';

    /**
     * A balance above zero is what the rider still owes; below zero, they
     * handed in more than they collected.
     *
     * With a KSA day (`Y-m-d`), only that day: what was collected on it, and the
     * payments staff recorded for it.
     *
     * @param  array<int>  $riderIds
     * @return array<int, array{collected: float, direct: float, card: float, transfer: float, paid: float, owed: array{cash: float, card: float, transfer: float}, balance: float}>
     */
    public static function forRiders(array $riderIds, ?string $day = null): array
    {
        $cash = [];
        foreach ($riderIds as $id) {
            $cash[$id] = ['collected' => 0.0, 'direct' => 0.0, 'card' => 0.0, 'transfer' => 0.0, 'paid' => 0.0, 'owed' => array_fill_keys(RiderPayment::COD_METHODS, 0.0), 'balance' => 0.0];
        }

        if ($riderIds === []) {
            return $cash;
        }

        // Every delivery the rider ever collected for, answered from
        // shipment_events_rider_cash_index without reading the rows.
        $collected = ShipmentEvent::whereIn('rider_id', $riderIds)
            ->where('action', RiderAction::DELIVERED->value)
            ->whereNotNull('payment_method')
            ->when($day, fn ($query) => $query->where('occurred_at', '>=', self::dayBounds($day)[0])->where('occurred_at', '<', self::dayBounds($day)[1]))
            ->selectRaw('rider_id, payment_method, COALESCE(SUM(cod_amount), 0) as total')
            ->groupBy('rider_id', 'payment_method')
            ->get();

        foreach ($collected as $row) {
            if ($row->payment_method === self::OWED_METHOD) {
                $cash[$row->rider_id]['collected'] += (float) $row->total;

                continue;
            }

            $cash[$row->rider_id]['direct'] += (float) $row->total;
            if (in_array($row->payment_method, ['card', 'transfer'], true)) {
                $cash[$row->rider_id][$row->payment_method] += (float) $row->total;
            }
        }

        $paid = RiderPayment::counted()
            ->handedIn()
            ->forDay($day)
            ->whereIn('rider_id', $riderIds)
            ->selectRaw('rider_id, cod_method, COALESCE(SUM(amount), 0) as total')
            ->groupBy('rider_id', 'cod_method')
            ->get();

        foreach ($cash as $riderId => $row) {
            $cash[$riderId]['owed'] = ['cash' => $row['collected'], 'card' => $row['card'], 'transfer' => $row['transfer']];
        }

        foreach ($paid as $row) {
            $cash[$row->rider_id]['paid'] += (float) $row->total;
            $cash[$row->rider_id]['owed'][in_array($row->cod_method, RiderPayment::COD_METHODS, true) ? $row->cod_method : 'cash'] -= (float) $row->total;
        }

        return array_map(fn (array $row) => [
            'collected' => round($row['collected'], 2),
            'direct' => round($row['direct'], 2),
            'card' => round($row['card'], 2),
            'transfer' => round($row['transfer'], 2),
            'paid' => round($row['paid'], 2),
            'owed' => array_map(fn (float $amount) => round($amount, 2), $row['owed']),
            'balance' => round($row['collected'] + $row['direct'] - $row['paid'], 2),
        ], $cash);
    }

    /**
     * @return array{collected: float, direct: float, card: float, transfer: float, paid: float, owed: array{cash: float, card: float, transfer: float}, balance: float}
     */
    public static function forRider(int $riderId, ?string $day = null): array
    {
        return self::forRiders([$riderId], $day)[$riderId];
    }

    /**
     * A KSA day as the instants it starts and stops.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayBounds(string $day): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $day, config('app.business_timezone', 'Asia/Riyadh'))->startOfDay();

        return [$start->copy()->utc(), $start->copy()->addDay()->utc()];
    }
}
