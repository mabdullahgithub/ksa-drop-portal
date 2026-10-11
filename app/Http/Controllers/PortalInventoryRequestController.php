<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ResolvesPortalClient;
use App\Models\InventoryRequest;
use App\Services\Inventory\InventoryRequestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Requests tab of a client's My Inventory page: what they asked to change
 * on their verified products, and what became of it.
 *
 * Requests are not made here. A client edits a product as usual and
 * PortalController::updateInventory turns that into one.
 */
class PortalInventoryRequestController extends Controller
{
    use ResolvesPortalClient;

    /** Requests per page. */
    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveFulfilmentClient();

        $validated = $request->validate([
            'status' => ['nullable', Rule::in([...InventoryRequest::STATUSES, 'all'])],
            'page' => 'nullable|integer|min:1',
        ]);

        $status = $validated['status'] ?? 'all';

        $requests = InventoryRequest::where('client_id', $client->id)
            ->with(InventoryRequestPresenter::relations())
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (InventoryRequest $inventoryRequest) => InventoryRequestPresenter::request($inventoryRequest, forTeam: false));

        return response()->json($requests->toArray() + [
            // Whatever the filter, for the number on the tab.
            'pending' => InventoryRequest::where('client_id', $client->id)->pending()->count(),
        ]);
    }

    /**
     * The client took a request back before anyone decided it.
     */
    public function destroy(InventoryRequest $inventoryRequest): JsonResponse
    {
        $client = $this->resolveFulfilmentClient();

        if ($inventoryRequest->client_id !== $client->id) {
            abort(403);
        }

        DB::transaction(function () use ($inventoryRequest) {
            $held = InventoryRequest::whereKey($inventoryRequest->id)->lockForUpdate()->firstOrFail();

            if (! $held->isPending()) {
                abort(422, "This request was already {$held->status}.");
            }

            $held->cancel();
        });

        return response()->json(['message' => 'Request cancelled']);
    }
}
