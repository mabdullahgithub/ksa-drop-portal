<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\StockScan;
use App\Services\Inventory\StockDayStats;
use App\Services\Inventory\StockScanPresenter;
use App\Services\Inventory\StockScanRecorder;
use App\Services\Inventory\StockScanRefused;
use App\Services\Riders\ParcelLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The inventory manager's half of the rider app: scan parcels OUT of and IN
 * to the warehouse.
 */
class RiderStockController extends Controller
{
    /** Scans per page of the home screen's log. */
    private const PER_PAGE = 20;

    public function __construct(private StockScanRecorder $recorder) {}

    /**
     * Home: how many parcels this manager scanned today, each way.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'today' => StockDayStats::forManager($this->manager($request)->id),
        ]);
    }

    /**
     * Home's log: one page of this manager's own scans, newest first. The
     * app asks for the next page as the list is scrolled.
     *
     * search: part of an order number (the "#" is optional) or of a tracking
     * number. direction: only OUT, or only IN.
     */
    public function scans(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:60',
            'direction' => ['nullable', Rule::in(StockScan::DIRECTIONS)],
            'page' => 'nullable|integer|min:1|max:1000',
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $search = ltrim(trim((string) ($validated['search'] ?? '')), '#');

        $scans = $this->manager($request)->stockScans()
            ->with(StockScanPresenter::RELATIONS)
            ->when($validated['direction'] ?? null, fn ($q, $direction) => $q->where('direction', $direction))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$search}%"))
                ->orWhereHas('shipment', fn ($q) => $q->where('tracking_number', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        return response()->json([
            'scans' => $scans->take(self::PER_PAGE)->map(fn (StockScan $scan) => StockScanPresenter::scan($scan))->values(),
            'next_page' => $scans->count() > self::PER_PAGE ? $page + 1 : null,
        ]);
    }

    /**
     * One scan, either way. Stock moves when `result` is the direction
     * itself; anything else changed nothing and says why.
     */
    public function scan(Request $request): JsonResponse
    {
        $manager = $this->manager($request);
        $validated = $request->validate([
            'direction' => ['required', Rule::in(StockScan::DIRECTIONS)],
            'code' => 'required|string|max:500',
            'client_uuid' => 'required|uuid',
            'entry_method' => 'nullable|in:camera,manual',
            'occurred_at' => 'nullable|date',
        ]);

        $code = ParcelLookup::normalize($validated['code']);
        $shipment = $code === '' ? null : ParcelLookup::anyCourier($code);

        // A parcel whose order was removed has nothing to count.
        if (! $shipment || ! $shipment->order) {
            return response()->json(['result' => 'not_found', 'message' => "No parcel found for {$code}.", 'code' => 'not_found'], 404);
        }

        try {
            $scan = $this->recorder->record($shipment, $manager, $validated['direction'], $validated);
        } catch (StockScanRefused $e) {
            return response()->json([
                'result' => $e->reason,
                'message' => $e->getMessage(),
                'parcel' => StockScanPresenter::parcel($shipment),
                // The scan that already put it where it is.
                'last_scan' => $e->last ? [
                    'occurred_at' => $e->last->occurred_at->toIso8601String(),
                    'scanned_by' => $e->last->rider?->name,
                ] : null,
            ]);
        }

        return response()->json([
            'result' => $scan->direction,
            'scan' => StockScanPresenter::scan($scan),
        ]);
    }

    private function manager(Request $request): Rider
    {
        return $request->attributes->get('rider');
    }
}
