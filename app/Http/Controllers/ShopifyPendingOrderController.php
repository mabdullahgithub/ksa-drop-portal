<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Jobs\RequestShopifyFulfillmentJob;
use App\Models\Order;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ShopifyPendingOrderController extends Controller
{
    public function __construct(private ShopifyFulfillmentService $fulfillment) {}

    /**
     * Resolve the acting client (impersonation-aware).
     */
    private function resolveClient(): ?Client
    {
        if (session()->has('impersonate.client_id')) {
            return Client::find(session('impersonate.client_id'));
        }

        return auth()->user()->client ?? null;
    }

    /**
     * Base query for this client's pending-review Shopify orders.
     * Bypasses the shopify_visible global scope so pending orders are returned.
     */
    private function pendingQuery(Client $client): Builder
    {
        return Order::withoutGlobalScope('shopify_visible')
            ->where('client_id', $client->id)
            ->where('shopify_sync_status', 'pending_review');
    }

    /**
     * Paginated list of orders awaiting client review.
     */
    public function index(Request $request)
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        $query = $this->pendingQuery($client)->with('items');

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString();

        return response()->json($orders);
    }

    /**
     * Approve a single pending order — it becomes a normal visible order.
     */
    public function submit(Request $request, int $orderId)
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        $order = $this->pendingQuery($client)->whereKey($orderId)->first();

        if (! $order) {
            return response()->json(['message' => 'Pending order not found.'], 404);
        }

        $order->update(['shopify_sync_status' => 'approved']);

        // Approval is the moment a manual-approval order becomes ours to move,
        // so it is also the moment we ask Shopify for it. Auto-sync stores do
        // this at import instead (ProcessShopifyWebhookJob).
        RequestShopifyFulfillmentJob::dispatch($order)->onConnection('database');

        return response()->json(['message' => 'Order submitted.', 'id' => $order->id]);
    }

    /**
     * Approve many pending orders at once.
     */
    public function submitBulk(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $client = $this->resolveClient();
        abort_unless($client, 403);

        $orders = $this->pendingQuery($client)
            ->whereIn('id', $validated['ids'])
            ->get();

        $submitted = $this->pendingQuery($client)
            ->whereIn('id', $validated['ids'])
            ->update(['shopify_sync_status' => 'approved']);

        // One job per order rather than one for the batch: each is an
        // independent call to Shopify, and a store-wide failure should not cost
        // the whole selection its request.
        foreach ($orders as $order) {
            RequestShopifyFulfillmentJob::dispatch($order->refresh())->onConnection('database');
        }

        return response()->json([
            'message'   => "{$submitted} order(s) submitted.",
            'submitted' => $submitted,
        ]);
    }

    /**
     * Dismiss a pending order — hidden everywhere, not imported.
     */
    public function dismiss(Request $request, int $orderId)
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        $order = $this->pendingQuery($client)->whereKey($orderId)->first();

        if (! $order) {
            return response()->json(['message' => 'Pending order not found.'], 404);
        }

        $order->update(['shopify_sync_status' => 'dismissed']);

        // Tell Shopify, if a request is open. A dismissed order that stays
        // "requested" in the merchant's admin forever tells them nothing, and
        // they find out we are not shipping it only when their customer asks.
        // Best-effort: the dismissal stands either way.
        if ($order->shopify_fulfillment_order_id) {
            $connection = $client->shopifyConnection;

            if ($connection) {
                $this->fulfillment->rejectRequest(
                    $connection,
                    $order->shopify_fulfillment_order_id,
                    'KSA Drop is not fulfilling this order.',
                );

                $order->update(['shopify_fulfillment_status' => 'rejected']);
            }
        }

        return response()->json(['message' => 'Order dismissed.', 'id' => $order->id]);
    }
}
