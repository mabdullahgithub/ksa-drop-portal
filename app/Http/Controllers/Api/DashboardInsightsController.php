<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\CityDirectory;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard's charts — orders per city, couriers ranked, the hours and
 * days orders come in, and stores ranked — over one shared period. Each chart
 * is a permission of its own, and a chart the person cannot see is not
 * computed, let alone sent.
 *
 * Every query goes through the Order / Shipment models, so the client-access
 * scope applies: a person limited to two clients sees only their orders here.
 */
class DashboardInsightsController extends Controller
{
    /** Period choices in days; 0 is "all time". */
    private const RANGES = [7, 30, 90, 0];

    // Every city, near enough: the regional split needs them all, and a row is tiny.
    private const TOP_CITIES = 300;
    private const TOP_STORES = 6;

    public function __invoke(Request $request, CityDirectory $cities): JsonResponse
    {
        $request->validate(['range' => ['nullable', 'integer', 'in:' . implode(',', self::RANGES)]]);

        $days = (int) $request->input('range', 0);
        // Days are business days (Riyadh), whatever the server clock says.
        $from = $days > 0 ? $this->today()->subDays($days - 1)->utc() : null;

        $user = $request->user();
        $revenue = $user->can('view dashboard revenue');

        return response()->json([
            'range' => $days,
            'cities' => $user->can('view dashboard city heatmap') ? $this->cities($cities, $from, $revenue) : null,
            'couriers' => $user->can('view dashboard courier ranking') ? $this->couriers($from) : null,
            'rhythm' => $user->can('view dashboard order rhythm') ? $this->rhythm($from) : null,
            'stores' => $user->can('view dashboard store ranking') ? $this->stores($from, $days, $revenue) : null,
        ]);
    }

    /**
     * Orders per real city. `shipping_city` is free text, so the raw values
     * are folded into cities by the same directory the City filter uses.
     */
    private function cities(CityDirectory $directory, ?Carbon $from, bool $revenue): array
    {
        $rows = $this->orders($from)
            ->whereNotNull('shipping_city')
            ->toBase()
            ->selectRaw(
                'shipping_city, count(*) as order_count, coalesce(sum(total), 0) as revenue,
                 coalesce(sum(case when exists (select 1 from shipments where shipments.order_id = orders.id and shipments.status = ?) then 1 else 0 end), 0) as delivered',
                [ShipmentStatus::DELIVERED->value]
            )
            ->groupBy('shipping_city')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $id = $directory->groupId($row->shipping_city);
            if ($id === '') {
                continue;
            }

            $group = &$groups[$id];
            $group['orders'] = ($group['orders'] ?? 0) + (int) $row->order_count;
            $group['revenue'] = ($group['revenue'] ?? 0) + (float) $row->revenue;
            $group['delivered'] = ($group['delivered'] ?? 0) + (int) $row->delivered;
            // The most common spelling labels a city the directory does not know.
            $group['spellings'][trim($row->shipping_city)] = ($group['spellings'][trim($row->shipping_city)] ?? 0) + (int) $row->order_count;
            unset($group);
        }

        $list = [];
        foreach ($groups as $id => $group) {
            arsort($group['spellings']);
            $known = $directory->groupId($id) === $id && CityDirectory::key($id) !== $id;

            $list[] = [
                'name' => $known ? $id : array_key_first($group['spellings']),
                'orders' => $group['orders'],
                'revenue' => $revenue ? round($group['revenue'], 2) : null,
                'delivered' => $group['delivered'],
            ];
        }

        usort($list, fn ($a, $b) => [$b['orders'], $a['name']] <=> [$a['orders'], $b['name']]);

        return [
            'total_orders' => array_sum(array_column($list, 'orders')),
            'city_count' => count($list),
            'items' => array_slice($list, 0, self::TOP_CITIES),
        ];
    }

    /**
     * Every courier's shipments in the period, ranked by delivery rate:
     * delivered out of every shipment handed to it. Cancelled shipments are
     * left out: they never reached a courier.
     */
    private function couriers(?Carbon $from): array
    {
        $delivered = ShipmentStatus::DELIVERED->value;
        // 'returning' is on its way back: the delivery has already failed.
        $lost = [ShipmentStatus::RETURNED->value, ShipmentStatus::FAILED->value, 'returning'];

        $rows = Shipment::query()
            ->when($from, fn ($q) => $q->where('shipments.created_at', '>=', $from))
            ->where('status', '<>', ShipmentStatus::CANCELLED->value)
            ->whereNotNull('courier')
            ->toBase()
            ->selectRaw(
                'courier, count(*) as total,
                 coalesce(sum(case when status = ? then 1 else 0 end), 0) as delivered,
                 coalesce(sum(case when status in (?, ?, ?) then 1 else 0 end), 0) as returned,
                 avg(case when status = ? and delivered_at is not null then ' . $this->hoursBetween('coalesce(shipped_at, created_at)', 'delivered_at') . ' end) as avg_hours',
                [$delivered, ...$lost, $delivered]
            )
            ->groupBy('courier')
            ->get();

        $list = $rows->map(function ($row) {
            $total = (int) $row->total;
            $delivered = (int) $row->delivered;
            $returned = (int) $row->returned;

            return [
                'courier' => $row->courier,
                'total' => $total,
                'delivered' => $delivered,
                'returned' => $returned,
                'in_progress' => $total - $delivered - $returned,
                'delivery_rate' => $total > 0 ? round($delivered / $total * 100, 1) : 0.0,
                'avg_delivery_hours' => $row->avg_hours !== null ? round(max(0, (float) $row->avg_hours), 1) : null,
            ];
        })->all();

        // Highest delivery rate first; on a tie, the courier that handled more.
        usort($list, fn ($a, $b) => [$b['delivery_rate'], $b['total']] <=> [$a['delivery_rate'], $a['total']]);

        return $list;
    }

    /**
     * When orders are placed, in business time: a count per hour of the day
     * and per day of the week (Sunday first, as the Saudi week runs).
     *
     * @return array{hours: list<int>, weekdays: list<int>}
     */
    private function rhythm(?Carbon $from): array
    {
        $local = $this->localDateTime('created_at');
        [$hour, $weekday] = DB::connection()->getDriverName() === 'sqlite'
            ? ["cast(strftime('%H', {$local}) as integer)", "cast(strftime('%w', {$local}) as integer)"]
            // dayofweek() is 1 for Sunday; shift it to 0 like SQLite's %w.
            : ["hour({$local})", "dayofweek({$local}) - 1"];

        $rows = $this->orders($from)
            ->toBase()
            ->selectRaw("{$hour} as hour, {$weekday} as weekday, count(*) as order_count")
            ->groupBy('hour', 'weekday')
            ->get();

        $hours = array_fill(0, 24, 0);
        $weekdays = array_fill(0, 7, 0);
        foreach ($rows as $row) {
            $hours[(int) $row->hour] += (int) $row->order_count;
            $weekdays[(int) $row->weekday] += (int) $row->order_count;
        }

        return ['hours' => $hours, 'weekdays' => $weekdays];
    }

    /**
     * Clients ranked by order value in the period, each with its order trend.
     * Without the revenue permission the ranking is by order count and no
     * money is sent.
     */
    private function stores(?Carbon $from, int $days, bool $revenue): array
    {
        $rows = $this->orders($from)
            ->whereNotNull('orders.client_id')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->toBase()
            ->selectRaw(
                'orders.client_id, clients.company_name, clients.short_id, clients.logo,
                 count(*) as order_count, coalesce(sum(orders.total), 0) as revenue,
                 coalesce(sum(case when exists (select 1 from shipments where shipments.order_id = orders.id and shipments.status = ?) then 1 else 0 end), 0) as delivered',
                [ShipmentStatus::DELIVERED->value]
            )
            ->groupBy('orders.client_id', 'clients.company_name', 'clients.short_id', 'clients.logo')
            ->orderByDesc($revenue ? 'revenue' : 'order_count')
            ->orderByDesc('order_count')
            ->limit(self::TOP_STORES)
            ->get();

        $trends = $this->trends($rows->pluck('client_id')->all(), $days);

        return $rows->map(fn ($row) => [
            'client_id' => (int) $row->client_id,
            'name' => $row->company_name,
            'short_id' => $row->short_id,
            'logo' => $row->logo ? '/storage/' . $row->logo : null,
            'orders' => (int) $row->order_count,
            'revenue' => $revenue ? round((float) $row->revenue, 2) : null,
            'delivered' => (int) $row->delivered,
            'trend' => $trends[$row->client_id] ?? [],
        ])->all();
    }

    /**
     * Orders per bucket for each client: days for a week or a month, weeks for
     * a quarter, months (the last twelve) for all time.
     *
     * @param  list<int>  $clientIds
     * @return array<int, list<int>>
     */
    private function trends(array $clientIds, int $days): array
    {
        if ($clientIds === []) {
            return [];
        }

        [$buckets, $unit] = match (true) {
            $days === 0 => [12, 'month'],
            $days > 31 => [(int) ceil($days / 7), 'week'],
            default => [$days, 'day'],
        };

        $start = match ($unit) {
            'month' => $this->today()->startOfMonth()->subMonths($buckets - 1),
            'week' => $this->today()->subDays($buckets * 7 - 1),
            default => $this->today()->subDays($buckets - 1),
        };

        $rows = Order::query()
            ->whereIn('client_id', $clientIds)
            ->where('created_at', '>=', $start->copy()->utc())
            ->toBase()
            ->selectRaw('client_id, ' . $this->localDate('created_at') . ' as day, count(*) as order_count')
            ->groupBy('client_id', 'day')
            ->get();

        $series = array_fill_keys($clientIds, array_fill(0, $buckets, 0));

        foreach ($rows as $row) {
            $day = Carbon::parse($row->day, $start->timezone);
            $index = match ($unit) {
                'month' => ($day->year - $start->year) * 12 + $day->month - $start->month,
                'week' => intdiv((int) $start->diffInDays($day), 7),
                default => (int) $start->diffInDays($day),
            };

            if ($index >= 0 && $index < $buckets) {
                $series[$row->client_id][$index] += (int) $row->order_count;
            }
        }

        return $series;
    }

    private function today(): Carbon
    {
        return now(config('app.business_timezone', 'Asia/Riyadh'))->startOfDay();
    }

    /** SQL for the business-timezone date of a UTC datetime column. */
    private function localDate(string $column): string
    {
        return "date({$this->localDateTime($column)})";
    }

    /** SQL for a UTC datetime column shifted to business time. */
    private function localDateTime(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "datetime({$column}, '" . $this->today()->utcOffset() . " minutes')"
            : "convert_tz({$column}, '+00:00', '{$this->today()->format('P')}')";
    }

    private function orders(?Carbon $from)
    {
        return Order::query()->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from));
    }

    /** SQL for the hours from one datetime column to another, on MySQL and SQLite alike. */
    private function hoursBetween(string $start, string $end): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday({$end}) - julianday({$start})) * 24"
            : "timestampdiff(second, {$start}, {$end}) / 3600";
    }
}
