<?php

namespace App\Services\Riders;

use App\Models\Order;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\FailedAttemptReason;
use App\Services\Shipping\Enums\RiderAction;
use App\Services\Shipping\Enums\ShipmentStatus;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Who delivered what between two dates — the "Top performers" section of the
 * portal's Riders page. Dates are whole days in the business timezone, read
 * the same way RiderDayStats reads "today".
 *
 * A parcel counts as assigned to a rider in the range when they scanned it
 * out, delivered it or failed an attempt on it in the range: every rider
 * update puts the parcel in their hands (ShipmentEventRecorder).
 *
 * Every query here is aggregated in SQL and rides one of the occurred_at
 * indexes on shipment_events; nothing loads rows it only counts.
 */
class RiderPerformance
{
    /**
     * Longest range anyone can ask for. Cost grows with the events in range
     * (index-only, but still every one of them); a quarter keeps the worst
     * case a fraction of a second.
     */
    public const MAX_DAYS = 92;

    /** Parcels per page of one rider's parcel list. */
    public const PARCELS_PER_PAGE = 25;

    /**
     * Where each parcel a rider handled ended up. "handed_back" is a parcel
     * no longer theirs: unassigned by an admin, or scanned by another rider
     * (who may have delivered it since).
     */
    public const OUTCOMES = ['delivered', 'out_for_delivery', 'attempt_fail', 'cancelled', 'returned', 'handed_back', 'other'];

    /**
     * The leaderboard: totals for every rider (not removed) who made an
     * update in the range, and the whole team's day-by-day numbers for the
     * trend's comparison line. Two scans of
     * shipment_events_occurred_covering_index, never touching table rows;
     * one rider's own days come from summary().
     *
     * @return array{
     *     days: list<string>,
     *     riders: array<int, array{assigned: int, delivered: int, failed: int, cod_collected: float}>,
     *     team_daily: array{delivered: list<int>, failed: list<int>, cod_collected: list<float>, riders: list<int>}
     * }
     */
    public static function between(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $days = self::days($start, $end);
        $empty = array_fill(0, count($days), 0);
        $team = ['delivered' => $empty, 'failed' => $empty, 'cod_collected' => $empty, 'riders' => $empty];

        // Riders is a handful of rows: filter removed ones by id rather than
        // joining it to every event.
        $riderIds = Rider::pluck('id')->all();
        if ($riderIds === []) {
            return ['days' => $days, 'riders' => [], 'team_daily' => $team];
        }

        [$delivered, $failed] = [RiderAction::DELIVERED->value, RiderAction::ATTEMPT_FAILED->value];

        $totals = self::inRange(ShipmentEvent::query(), $start, $end)
            ->whereIn('rider_id', $riderIds)
            ->selectRaw(
                'rider_id, COUNT(DISTINCT shipment_id) as assigned, '
                . 'SUM(CASE WHEN action = ? THEN 1 ELSE 0 END) as delivered, '
                . 'SUM(CASE WHEN action = ? THEN 1 ELSE 0 END) as failed, '
                . 'COALESCE(SUM(CASE WHEN action = ? THEN cod_amount END), 0) as cod',
                [$delivered, $failed, $delivered]
            )
            ->groupBy('rider_id')
            ->get();

        $riders = [];
        foreach ($totals as $row) {
            $riders[$row->rider_id] = [
                'assigned' => (int) $row->assigned,
                'delivered' => (int) $row->delivered,
                'failed' => (int) $row->failed,
                'cod_collected' => round((float) $row->cod, 2),
            ];
        }

        if ($riders === []) {
            return ['days' => $days, 'riders' => [], 'team_daily' => $team];
        }

        $day = self::localDay($start->getOffset());
        $index = array_flip($days);

        $daily = self::inRange(ShipmentEvent::query(), $start, $end)
            ->whereIn('rider_id', array_keys($riders))
            ->whereIn('action', [$delivered, $failed])
            ->selectRaw(
                "{$day} as local_day, "
                . 'SUM(CASE WHEN action = ? THEN 1 ELSE 0 END) as delivered, '
                . 'SUM(CASE WHEN action = ? THEN 1 ELSE 0 END) as failed, '
                . 'COALESCE(SUM(CASE WHEN action = ? THEN cod_amount END), 0) as cod, '
                . 'COUNT(DISTINCT rider_id) as riders',
                [$delivered, $failed, $delivered]
            )
            ->groupBy('local_day')
            ->get();

        foreach ($daily as $row) {
            if (($i = $index[$row->local_day] ?? null) === null) {
                continue;
            }
            $team['delivered'][$i] = (int) $row->delivered;
            $team['failed'][$i] = (int) $row->failed;
            $team['cod_collected'][$i] = round((float) $row->cod, 2);
            $team['riders'][$i] = (int) $row->riders;
        }

        return ['days' => $days, 'riders' => $riders, 'team_daily' => $team];
    }

    /**
     * One rider's range in three small aggregates over their own index
     * entries: where their parcels ended up, why attempts failed, and their
     * day-by-day numbers. The parcel list itself is parcels().
     *
     * @return array{
     *     assigned: int,
     *     outcomes: array<string, int>,
     *     failed_reasons: list<array{reason: string, label: string, count: int}>,
     *     daily: array{delivered: list<int>, failed: list<int>, cod_collected: list<float>}
     * }
     */
    public static function summary(Rider $rider, string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $days = self::days($start, $end);
        $index = array_flip($days);
        $empty = array_fill(0, count($days), 0);
        $daily = ['delivered' => $empty, 'failed' => $empty, 'cod_collected' => $empty];

        [$delivered, $failed] = [RiderAction::DELIVERED->value, RiderAction::ATTEMPT_FAILED->value];
        $day = self::localDay($start->getOffset());

        $rows = self::inRange(ShipmentEvent::where('rider_id', $rider->id), $start, $end)
            ->whereIn('action', [$delivered, $failed])
            ->selectRaw(
                "{$day} as local_day, "
                . 'SUM(CASE WHEN action = ? THEN 1 ELSE 0 END) as delivered, '
                . 'SUM(CASE WHEN action = ? THEN 1 ELSE 0 END) as failed, '
                . 'COALESCE(SUM(CASE WHEN action = ? THEN cod_amount END), 0) as cod',
                [$delivered, $failed, $delivered]
            )
            ->groupBy('local_day')
            ->get();

        foreach ($rows as $row) {
            if (($i = $index[$row->local_day] ?? null) === null) {
                continue;
            }
            $daily['delivered'][$i] = (int) $row->delivered;
            $daily['failed'][$i] = (int) $row->failed;
            $daily['cod_collected'][$i] = round((float) $row->cod, 2);
        }

        $handled = self::inRange(ShipmentEvent::where('rider_id', $rider->id), $start, $end)
            ->distinct()
            ->select('shipment_id');

        $counts = Shipment::query()
            ->joinSub($handled, 'handled', 'handled.shipment_id', '=', 'shipments.id')
            ->selectRaw(self::outcomeSql($rider) . ' as outcome, COUNT(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        $outcomes = array_fill_keys(self::OUTCOMES, 0);
        foreach ($counts as $outcome => $total) {
            $outcomes[$outcome] = (int) $total;
        }

        $reasons = self::inRange(ShipmentEvent::where('rider_id', $rider->id), $start, $end)
            ->where('action', RiderAction::ATTEMPT_FAILED->value)
            ->selectRaw('reason, COUNT(*) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get();

        return [
            'assigned' => array_sum($outcomes),
            'outcomes' => $outcomes,
            'failed_reasons' => $reasons->map(fn ($row) => [
                'reason' => $row->reason ?: 'none',
                'label' => FailedAttemptReason::tryFrom((string) $row->reason)?->label() ?? 'No reason given',
                'count' => (int) $row->total,
            ])->values()->all(),
            'daily' => $daily,
        ];
    }

    /**
     * One page of the rider's parcels, most recently touched first, with
     * their updates in the range. Filtering happens in SQL; only the page's
     * rows and the columns shown are read.
     *
     * @return array{parcels: list<array>, next_page: ?int}
     */
    public static function parcels(Rider $rider, string $from, string $to, ?string $outcome = null, ?string $search = null, int $page = 1): array
    {
        [$start, $end] = self::bounds($from, $to);

        $latest = self::inRange(ShipmentEvent::where('rider_id', $rider->id), $start, $end)
            ->groupBy('shipment_id')
            ->selectRaw('shipment_id, MAX(occurred_at) as last_at');

        $outcomeSql = self::outcomeSql($rider);

        $rows = Shipment::query()
            ->joinSub($latest, 'mine', 'mine.shipment_id', '=', 'shipments.id')
            ->leftJoin('orders', 'orders.id', '=', 'shipments.order_id')
            ->leftJoin('riders as holder', 'holder.id', '=', 'shipments.rider_id')
            ->when($outcome, fn (Builder $q) => $q->whereRaw("{$outcomeSql} = ?", [$outcome]))
            ->when(filled($search), function (Builder $q) use ($search) {
                $like = '%' . addcslashes(trim($search), '%_\\') . '%';
                $q->where(fn (Builder $q) => $q
                    ->where('shipments.tracking_number', 'like', $like)
                    ->orWhere('orders.order_number', 'like', $like));
            })
            ->select([
                'shipments.id',
                'shipments.rider_id',
                'shipments.tracking_number',
                'shipments.status',
                'shipments.cancel_reason',
                'shipments.cancelled_at',
                'shipments.hub_received_at',
                'shipments.api_response',
                'mine.last_at',
                'orders.order_number as order_number',
                'orders.shipping_city as order_city',
                'orders.payment_method as order_payment_method',
                'orders.financial_status as order_financial_status',
                'orders.total as order_total',
                'holder.name as holder_name',
            ])
            ->selectRaw("{$outcomeSql} as outcome")
            ->orderByDesc('mine.last_at')
            ->orderByDesc('shipments.id')
            ->offset(($page - 1) * self::PARCELS_PER_PAGE)
            ->limit(self::PARCELS_PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PARCELS_PER_PAGE;
        $rows = $rows->take(self::PARCELS_PER_PAGE);

        $events = $rows->isEmpty() ? collect() : self::inRange(ShipmentEvent::where('rider_id', $rider->id), $start, $end)
            ->whereIn('shipment_id', $rows->pluck('id'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'shipment_id', 'action', 'reason', 'reschedule_date', 'note', 'cod_amount', 'payment_method', 'photo_path', 'lat', 'lng', 'occurred_at'])
            ->groupBy('shipment_id');

        $parcels = $rows->map(function (Shipment $shipment) use ($events) {
            // The COD fallback needs just these order fields; no second query.
            $shipment->setRelation('order', (new Order)->forceFill([
                'payment_method' => $shipment->order_payment_method,
                'financial_status' => $shipment->order_financial_status,
                'total' => $shipment->order_total,
            ]));
            $ended = in_array($shipment->outcome, ['cancelled', 'returned'], true);

            return [
                'id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'order_number' => $shipment->order_number,
                'city' => ($shipment->api_response['receiver']['city'] ?? null) ?: $shipment->order_city,
                'cod_amount' => $shipment->expectedCodAmount(),
                'status' => $shipment->status,
                'status_label' => $shipment->status_label,
                'outcome' => $shipment->outcome,
                'held_by' => $shipment->outcome === 'handed_back' ? $shipment->holder_name : null,
                'cancel_reason' => $ended ? $shipment->cancel_reason : null,
                'cancelled_at' => $ended ? $shipment->cancelled_at?->toIso8601String() : null,
                // Returned or cancelled: still with the rider until this is set.
                'awaiting_hand_back' => $shipment->awaitsHandBack(),
                'hub_received_at' => $ended ? $shipment->hub_received_at?->toIso8601String() : null,
                'events' => ($events[$shipment->id] ?? collect())->map(fn (ShipmentEvent $event) => [
                    'action' => $event->action,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                    'reason' => FailedAttemptReason::tryFrom((string) $event->reason)?->label(),
                    'reschedule_date' => $event->reschedule_date?->toDateString(),
                    'note' => $event->note,
                    'cod_amount' => $event->cod_amount !== null ? (float) $event->cod_amount : null,
                    'payment_method' => $event->payment_method,
                    // Proof: the rider's photo, and where the phone was.
                    'photo_url' => $event->photo_path !== null ? route('api.shipment-events.photo', $event->id) : null,
                    'lat' => $event->lat !== null ? (float) $event->lat : null,
                    'lng' => $event->lng !== null ? (float) $event->lng : null,
                ])->values()->all(),
            ];
        })->values()->all();

        return ['parcels' => $parcels, 'next_page' => $hasMore ? $page + 1 : null];
    }

    /**
     * SQL naming where a parcel ended up for this rider — the one definition
     * the summary counts and the parcel list filter both use.
     */
    private static function outcomeSql(Rider $rider): string
    {
        $id = (int) $rider->id;
        $status = fn (ShipmentStatus $s) => "'" . $s->value . "'";

        return 'CASE'
            . ' WHEN shipments.status = ' . $status(ShipmentStatus::CANCELLED) . " THEN 'cancelled'"
            . ' WHEN shipments.status = ' . $status(ShipmentStatus::RETURNED) . " THEN 'returned'"
            . " WHEN shipments.rider_id IS NULL OR shipments.rider_id <> {$id} THEN 'handed_back'"
            . ' WHEN shipments.status = ' . $status(ShipmentStatus::DELIVERED) . " THEN 'delivered'"
            . ' WHEN shipments.status = ' . $status(ShipmentStatus::OUT_FOR_DELIVERY) . " THEN 'out_for_delivery'"
            . ' WHEN shipments.status = ' . $status(ShipmentStatus::ATTEMPT_FAIL) . " THEN 'attempt_fail'"
            . " ELSE 'other' END";
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Start and end of the range in the business timezone.
     */
    private static function bounds(string $from, string $to): array
    {
        $timezone = config('app.business_timezone', 'Asia/Riyadh');

        return [
            Carbon::createFromFormat('Y-m-d', $from, $timezone)->startOfDay(),
            Carbon::createFromFormat('Y-m-d', $to, $timezone)->endOfDay(),
        ];
    }

    /** @return list<string> Every KSA day in the range, `Y-m-d`. */
    private static function days(Carbon $start, Carbon $end): array
    {
        return array_map(
            fn (CarbonInterface $day) => $day->toDateString(),
            CarbonPeriod::create($start->toDateString(), $end->toDateString())->toArray()
        );
    }

    private static function inRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('shipment_events.occurred_at', [$start->copy()->utc(), $end->copy()->utc()]);
    }

    /**
     * SQL for an event's calendar day in the business timezone. One fixed
     * offset for the whole range is exact for Asia/Riyadh, which has no DST.
     */
    private static function localDay(int $offsetSeconds): string
    {
        $column = 'shipment_events.occurred_at';

        return match ((new ShipmentEvent)->getConnection()->getDriverName()) {
            'sqlite' => sprintf("date(%s, '%+d seconds')", $column, $offsetSeconds),
            'pgsql' => sprintf("DATE(%s + INTERVAL '%d seconds')", $column, $offsetSeconds),
            default => sprintf('DATE(DATE_ADD(%s, INTERVAL %d SECOND))', $column, $offsetSeconds),
        };
    }
}
