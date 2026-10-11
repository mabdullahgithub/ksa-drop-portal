<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureRequestsUnlocked;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Services\Inventory\ClientProductReviewer;
use App\Services\Inventory\InventoryRequestPresenter;
use App\Services\Inventory\InventoryRequestRefused;
use App\Services\Inventory\InventoryRequestReviewer;
use App\Services\Inventory\RequestQueues;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * The Requests page: everything clients are waiting on, in three queues.
 *
 * New products are client products still pending verification. Stock updates
 * and details changes are what clients asked to change on verified ones
 * (InventoryRequest). Each queue, and each button in it, is a permission of
 * its own; the routes carry them.
 */
class InventoryRequestController extends Controller
{
    /** Rows per page of a queue. */
    private const PER_PAGE = 25;

    /** Most requests one bulk action takes. */
    private const BULK_LIMIT = 100;

    public function __construct(private InventoryRequestReviewer $reviewer) {}

    /**
     * Exchange the PIN for an unlocked session; see EnsureRequestsUnlocked.
     * It is the recycle bin's PIN — one PIN for every locked screen.
     */
    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|max:32',
        ]);

        $expected = (string) config('recyclebin.pin');

        if ($expected === '' || ! hash_equals($expected, $validated['pin'])) {
            Log::warning('Requests page unlock failed', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Incorrect PIN.'], 422);
        }

        $request->session()->put(EnsureRequestsUnlocked::SESSION_KEY, now());

        return response()->json([
            'message' => 'Requests unlocked',
            'expires_in_minutes' => (int) config('locks.requests_unlock_ttl'),
        ]);
    }

    /**
     * Drop the unlock, so leaving the page re-locks it for the next visit.
     */
    public function lock(Request $request): JsonResponse
    {
        $request->session()->forget(EnsureRequestsUnlocked::SESSION_KEY);

        return response()->json(['message' => 'Requests locked']);
    }

    /**
     * What is waiting in each queue the person can open, and the clients to
     * filter by.
     */
    public function counts(Request $request): JsonResponse
    {
        return response()->json([
            'counts' => RequestQueues::counts($request->user()),
            // Only fulfilment clients keep stock with us.
            'clients' => Client::whereJsonContains('client_types', 'fulfilment')
                ->orderBy('company_name')
                ->get()
                ->map(fn (Client $client) => ['id' => $client->id, 'company_name' => $client->company_name])
                ->values(),
        ]);
    }

    // ---------------------------------------------------------- new products

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'verified', 'rejected', 'all'])],
            'client_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $status = $validated['status'] ?? 'pending';
        $search = trim((string) ($validated['search'] ?? ''));

        $products = ClientProduct::query()
            ->whereHas('client')
            ->with(['client', 'images'])
            ->when($status !== 'all', fn ($query) => $query->where('verification_status', $status))
            ->when($validated['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->search($search)
                ->orWhereHas('client', fn ($query) => $query->where('company_name', 'like', "%{$search}%"))));

        // The queue is worked oldest first; the history reads newest first.
        $products = $status === 'pending'
            ? $products->orderBy('created_at')->orderBy('id')
            : $products->orderByDesc('updated_at')->orderByDesc('id');

        return response()->json($products->paginate(self::PER_PAGE)->through(fn (ClientProduct $product) => [
            ...$product->only([
                'id', 'client_id', 'product_code', 'name', 'sku', 'description', 'quantity', 'unit_price',
                'notes', 'verification_status', 'verified_at', 'rejection_reason', 'is_out_of_stock',
            ]),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'images' => $product->images,
            'client' => ['id' => $product->client->id, 'company_name' => $product->client->company_name],
        ]));
    }

    public function reviewProduct(Request $request, ClientProduct $product): JsonResponse
    {
        return response()->json([
            'message' => 'Product reviewed successfully',
            'product' => app(ClientProductReviewer::class)->reviewFromRequest($request, $product),
        ]);
    }

    // --------------------------------------------------------- stock updates

    public function stock(Request $request): JsonResponse
    {
        return $this->queue($request, InventoryRequest::STOCK);
    }

    public function approveStock(Request $request, InventoryRequest $inventoryRequest): JsonResponse
    {
        $validated = $request->validate([
            // The count that really arrived (or, for a request to lower
            // stock, the count to take off).
            'units' => 'required|integer|min:1',
            'mark_in_stock' => 'nullable|boolean',
            'revision' => 'nullable|integer',
        ]);

        return $this->decide(fn () => $this->reviewer->approveStock(
            $inventoryRequest,
            (int) $validated['units'],
            (bool) ($validated['mark_in_stock'] ?? false),
            $validated['revision'] ?? null,
        ), 'Stock update approved');
    }

    public function rejectStock(Request $request, InventoryRequest $inventoryRequest): JsonResponse
    {
        return $this->reject($request, $inventoryRequest, InventoryRequest::STOCK);
    }

    public function bulkApproveStock(Request $request): JsonResponse
    {
        return $this->bulk($request, InventoryRequest::STOCK, fn (InventoryRequest $inventoryRequest) => $this->reviewer->approveStock(
            $inventoryRequest,
            // As requested: there is no one count to type for several.
            abs($inventoryRequest->requestedChange()),
        ));
    }

    public function bulkRejectStock(Request $request): JsonResponse
    {
        return $this->bulkReject($request, InventoryRequest::STOCK);
    }

    // ------------------------------------------------------- details changes

    public function details(Request $request): JsonResponse
    {
        return $this->queue($request, InventoryRequest::DETAILS);
    }

    public function approveDetails(Request $request, InventoryRequest $inventoryRequest): JsonResponse
    {
        $validated = $request->validate(['revision' => 'nullable|integer']);

        return $this->decide(
            fn () => $this->reviewer->approveDetails($inventoryRequest, $validated['revision'] ?? null),
            'Details change approved',
        );
    }

    public function rejectDetails(Request $request, InventoryRequest $inventoryRequest): JsonResponse
    {
        return $this->reject($request, $inventoryRequest, InventoryRequest::DETAILS);
    }

    public function bulkApproveDetails(Request $request): JsonResponse
    {
        return $this->bulk($request, InventoryRequest::DETAILS, fn (InventoryRequest $inventoryRequest) => $this->reviewer->approveDetails($inventoryRequest));
    }

    public function bulkRejectDetails(Request $request): JsonResponse
    {
        return $this->bulkReject($request, InventoryRequest::DETAILS);
    }

    // ---------------------------------------------------------------- shared

    /**
     * One page of a queue.
     */
    private function queue(Request $request, string $type): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([...InventoryRequest::STATUSES, 'all'])],
            'client_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $status = $validated['status'] ?? InventoryRequest::PENDING;
        $search = trim((string) ($validated['search'] ?? ''));

        $requests = $this->ofType($type)
            ->with(InventoryRequestPresenter::relations())
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($validated['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereHas('product', fn ($query) => $query->withTrashed()->search($search))
                ->orWhereHas('client', fn ($query) => $query->where('company_name', 'like', "%{$search}%"))));

        // The queue is worked oldest first; the history reads newest first.
        $requests = $status === InventoryRequest::PENDING
            ? $requests->orderBy('created_at')->orderBy('id')
            : $requests->orderByDesc('updated_at')->orderByDesc('id');

        return response()->json($requests->paginate(self::PER_PAGE)
            ->through(fn (InventoryRequest $inventoryRequest) => InventoryRequestPresenter::request($inventoryRequest)));
    }

    /**
     * A queue's requests. A client in the recycle bin takes its requests out
     * of sight with it; they are back when it is restored.
     */
    private function ofType(string $type): Builder
    {
        return InventoryRequest::where('type', $type)->whereHas('client');
    }

    private function reject(Request $request, InventoryRequest $inventoryRequest, string $type): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'revision' => 'nullable|integer',
        ]);

        return $this->decide(
            fn () => $this->reviewer->reject($inventoryRequest, $type, $validated['reason'], $validated['revision'] ?? null),
            'Request rejected',
        );
    }

    /**
     * @param  callable(): InventoryRequest  $decision
     */
    private function decide(callable $decision, string $message): JsonResponse
    {
        try {
            $inventoryRequest = $decision();
        } catch (InventoryRequestRefused $refused) {
            return response()->json(['message' => $refused->getMessage(), 'reason' => $refused->reason], $refused->status);
        }

        return response()->json([
            'message' => $message,
            'request' => InventoryRequestPresenter::request($inventoryRequest->fresh()),
        ]);
    }

    private function bulkReject(Request $request, string $type): JsonResponse
    {
        $reason = $request->validate(['reason' => 'required|string|max:1000'])['reason'];

        return $this->bulk($request, $type, fn (InventoryRequest $inventoryRequest) => $this->reviewer->reject($inventoryRequest, $type, $reason));
    }

    /**
     * The same decision on several requests. Each stands alone: one that
     * can't go through is reported and the rest carry on.
     *
     * @param  callable(InventoryRequest): InventoryRequest  $decision
     */
    private function bulk(Request $request, string $type, callable $decision): JsonResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array|min:1|max:' . self::BULK_LIMIT,
            'ids.*' => 'integer',
        ])['ids'];

        $found = $this->ofType($type)->whereIn('id', $ids)->with('product')->get()->keyBy('id');

        $done = 0;
        $skipped = [];

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $inventoryRequest = $found->get($id);

            if (! $inventoryRequest) {
                $skipped[] = ['id' => $id, 'product' => null, 'message' => 'Not found.'];

                continue;
            }

            try {
                $decision($inventoryRequest);
                $done++;
            } catch (InventoryRequestRefused $refused) {
                $skipped[] = ['id' => $id, 'product' => $inventoryRequest->product?->name, 'message' => $refused->getMessage()];
            }
        }

        return response()->json(['done' => $done, 'skipped' => $skipped]);
    }
}
