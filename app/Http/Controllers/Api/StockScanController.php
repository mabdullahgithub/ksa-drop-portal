<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockScan;
use App\Services\Inventory\StockScanPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The Inventory page's scan log: every parcel the inventory managers scanned
 * OUT of or IN to the warehouse, and what each scan did to stock.
 */
class StockScanController extends Controller
{
    /** Scans per page of the log. */
    private const PER_PAGE = 25;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['nullable', Rule::in(StockScan::DIRECTIONS)],
            // KSA days, both inclusive.
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:60',
            // Only scans with an item that matched no product.
            'unmatched' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1|max:1000',
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $timezone = config('app.business_timezone', 'Asia/Riyadh');
        $search = trim((string) ($validated['search'] ?? ''));

        $scans = StockScan::query()
            ->with(StockScanPresenter::RELATIONS)
            ->when($validated['direction'] ?? null, fn ($q, $direction) => $q->where('direction', $direction))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q
                ->where('occurred_at', '>=', Carbon::createFromFormat('Y-m-d', $from, $timezone)->startOfDay()->utc()))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q
                ->where('occurred_at', '<', Carbon::createFromFormat('Y-m-d', $to, $timezone)->addDay()->startOfDay()->utc()))
            ->when($request->boolean('unmatched'), fn ($q) => $q
                ->whereHas('items', fn ($q) => $q->whereNull('product_id')->whereNull('client_product_id')))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('shipment', fn ($q) => $q->where('tracking_number', 'like', "%{$search}%"))
                ->orWhereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$search}%"))
                ->orWhereHas('items', fn ($q) => $q->where('sku', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        return response()->json([
            'scans' => $scans->take(self::PER_PAGE)->map(fn (StockScan $scan) => StockScanPresenter::scan($scan))->values(),
            'next_page' => $scans->count() > self::PER_PAGE ? $page + 1 : null,
        ]);
    }
}
