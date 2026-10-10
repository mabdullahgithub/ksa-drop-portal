<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\StockScan;
use App\Services\Inventory\StockScanPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The portal's scan logs: every parcel the inventory managers scanned OUT of
 * or IN to the warehouse, and what each scan did to stock. The Inventory page
 * lists all of them; the Riders page lists one manager's.
 */
class StockScanController extends Controller
{
    /** Scans per page of the log. */
    private const PER_PAGE = 25;

    public function index(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        return response()->json($this->page($this->inRange(StockScan::query(), $validated), $validated));
    }

    /**
     * One inventory manager's own scans, from their card on the Riders page.
     * The first page also carries what they scanned in the dates asked for,
     * each way: the counts beside the filters.
     */
    public function manager(Request $request, Rider $rider): JsonResponse
    {
        $validated = $this->validated($request);
        $scans = $this->inRange(StockScan::where('rider_id', $rider->id), $validated);

        return response()->json($this->page(clone $scans, $validated) + [
            'totals' => (int) ($validated['page'] ?? 1) === 1 ? $this->totals($scans) : null,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'direction' => ['nullable', Rule::in(StockScan::DIRECTIONS)],
            // KSA days, both inclusive.
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:60',
            // Only scans with an item that matched no product.
            'unmatched' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1|max:1000',
        ]);
    }

    private function inRange(Builder $scans, array $validated): Builder
    {
        $timezone = config('app.business_timezone', 'Asia/Riyadh');

        return $scans
            ->when($validated['from'] ?? null, fn ($q, $from) => $q
                ->where('occurred_at', '>=', Carbon::createFromFormat('Y-m-d', $from, $timezone)->startOfDay()->utc()))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q
                ->where('occurred_at', '<', Carbon::createFromFormat('Y-m-d', $to, $timezone)->addDay()->startOfDay()->utc()));
    }

    /**
     * One page of `$scans`, newest first, narrowed by the filters and search.
     *
     * @return array{scans: mixed, next_page: ?int}
     */
    private function page(Builder $scans, array $validated): array
    {
        $page = (int) ($validated['page'] ?? 1);
        $search = trim((string) ($validated['search'] ?? ''));

        $scans = $scans
            ->with(StockScanPresenter::RELATIONS)
            ->when($validated['direction'] ?? null, fn ($q, $direction) => $q->where('direction', $direction))
            ->when($validated['unmatched'] ?? false, fn ($q) => $this->withUnmatchedItem($q))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('shipment', fn ($q) => $q->where('tracking_number', 'like', "%{$search}%"))
                ->orWhereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$search}%"))
                ->orWhereHas('items', fn ($q) => $q->where('sku', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        return [
            'scans' => $scans->take(self::PER_PAGE)->map(fn (StockScan $scan) => StockScanPresenter::scan($scan))->values(),
            'next_page' => $scans->count() > self::PER_PAGE ? $page + 1 : null,
        ];
    }

    /**
     * @return array{out: array{parcels: int, pieces: int}, in: array{parcels: int, pieces: int}, unmatched: int}
     */
    private function totals(Builder $scans): array
    {
        $totals = [
            StockScan::OUT => ['parcels' => 0, 'pieces' => 0],
            StockScan::IN => ['parcels' => 0, 'pieces' => 0],
        ];

        $rows = (clone $scans)->toBase()
            ->selectRaw('direction, COUNT(*) as parcels, COALESCE(SUM(pieces), 0) as pieces')
            ->groupBy('direction')
            ->get();

        foreach ($rows as $row) {
            $totals[$row->direction] = ['parcels' => (int) $row->parcels, 'pieces' => (int) $row->pieces];
        }

        return $totals + ['unmatched' => $this->withUnmatchedItem(clone $scans)->count()];
    }

    /** Scans with an item that matched no product. */
    private function withUnmatchedItem(Builder $scans): Builder
    {
        return $scans->whereHas('items', fn ($q) => $q->whereNull('product_id')->whereNull('client_product_id'));
    }
}
