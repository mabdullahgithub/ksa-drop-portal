<?php

namespace App\Services\Riders;

use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\RiderPayment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\RiderAction;

/**
 * What KSA Drop pays its riders, per visit to a customer:
 *
 *  - delivered: the delivery rate;
 *  - the rider went and the customer didn't take it (a failed attempt): the
 *    attempt rate, every time — proved by the photo of the place the rider
 *    takes there (see RiderAction::needsProof());
 *  - returned or cancelled: nothing. Those close the order after the
 *    attempts, which were each paid already.
 *
 * So an order that failed twice and was delivered on the third visit earns
 * two attempts and a delivery.
 *
 * Every rider has their own two rates, agreed with them and set by the admin
 * in their details. The balance is earnings minus the payouts staff
 * recorded; like RiderCash it is worked out from the records, never stored.
 */
class RiderPay
{
    /**
     * What a delivery and an attempt pay this rider. Each rider has their
     * own, set by the admin in their details; nothing until then.
     *
     * @return array{delivery: float, attempt: float}
     */
    public static function ratesFor(Rider $rider): array
    {
        return [
            'delivery' => (float) ($rider->delivery_rate ?? 0),
            'attempt' => (float) ($rider->attempt_rate ?? 0),
        ];
    }

    /**
     * Called for every rider update, inside its transaction.
     */
    public static function record(Rider $rider, ShipmentEvent $event): void
    {
        $action = RiderAction::tryFrom($event->action);

        $type = match (true) {
            $action === RiderAction::DELIVERED => RiderEarning::TYPE_DELIVERED,
            // Without the photo there's no saying the rider went.
            $action?->needsProof() && $event->photo_path !== null => RiderEarning::TYPE_ATTEMPTED,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $rates = self::ratesFor($rider);

        RiderEarning::firstOrCreate(['shipment_event_id' => $event->id], [
            'rider_id' => $rider->id,
            'shipment_id' => $event->shipment_id,
            'type' => $type,
            'amount' => $type === RiderEarning::TYPE_DELIVERED ? $rates['delivery'] : $rates['attempt'],
            'earned_at' => $event->occurred_at,
        ]);
    }

    /**
     * A balance above zero is what KSA Drop still owes the rider. Earnings
     * are also split by what earned them, and payouts by how they were paid.
     *
     * @param  array<int>  $riderIds
     * @return array<int, array{earned: float, earned_delivered: float, earned_attempted: float, paid: float, paid_by: array{cash: float, bank_transfer: float, other: float}, balance: float, delivered: int, attempted: int}>
     */
    public static function forRiders(array $riderIds): array
    {
        $pay = [];
        foreach ($riderIds as $id) {
            $pay[$id] = [
                'earned' => 0.0, 'earned_delivered' => 0.0, 'earned_attempted' => 0.0,
                'paid' => 0.0, 'paid_by' => array_fill_keys(RiderPayment::METHODS, 0.0),
                'balance' => 0.0, 'delivered' => 0, 'attempted' => 0,
            ];
        }

        if ($riderIds === []) {
            return $pay;
        }

        $earned = RiderEarning::whereIn('rider_id', $riderIds)
            ->selectRaw('rider_id, type, COUNT(*) as visits, COALESCE(SUM(amount), 0) as total')
            ->groupBy('rider_id', 'type')
            ->get();

        foreach ($earned as $row) {
            $kind = $row->type === RiderEarning::TYPE_DELIVERED ? 'delivered' : 'attempted';
            $pay[$row->rider_id]['earned'] += (float) $row->total;
            $pay[$row->rider_id]["earned_{$kind}"] += (float) $row->total;
            $pay[$row->rider_id][$kind] += (int) $row->visits;
        }

        $paid = RiderPayment::counted()
            ->paidOut()
            ->whereIn('rider_id', $riderIds)
            ->selectRaw('rider_id, method, COALESCE(SUM(amount), 0) as total')
            ->groupBy('rider_id', 'method')
            ->get();

        foreach ($paid as $row) {
            $pay[$row->rider_id]['paid'] += (float) $row->total;
            $pay[$row->rider_id]['paid_by'][$row->method] = (float) $row->total;
        }

        return array_map(fn (array $row) => [
            'earned' => round($row['earned'], 2),
            'earned_delivered' => round($row['earned_delivered'], 2),
            'earned_attempted' => round($row['earned_attempted'], 2),
            'paid' => round($row['paid'], 2),
            'paid_by' => array_map(fn (float $amount) => round($amount, 2), $row['paid_by']),
            'balance' => round($row['earned'] - $row['paid'], 2),
            'delivered' => $row['delivered'],
            'attempted' => $row['attempted'],
        ], $pay);
    }

    /**
     * @return array{earned: float, earned_delivered: float, earned_attempted: float, paid: float, paid_by: array{cash: float, bank_transfer: float, other: float}, balance: float, delivered: int, attempted: int}
     */
    public static function forRider(int $riderId): array
    {
        return self::forRiders([$riderId])[$riderId];
    }
}
