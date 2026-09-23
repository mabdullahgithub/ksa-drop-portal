<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithTrashedRecords;
use App\Http\Middleware\EnsureRecycleBinUnlocked;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * The recycle bin: everything soft-deleted from the portal, with restore and
 * permanent delete.
 *
 * Methods are explicit per entity rather than keyed off a {type} route
 * parameter, because CheckPermission takes a static string. A polymorphic
 * route could only be gated on canAny across all three permissions, which
 * would let someone holding just "delete inventory" purge clients.
 */
class RecycleBinController extends Controller
{
    use InteractsWithTrashedRecords;

    public function page()
    {
        return Inertia::render('RecycleBin');
    }

    /**
     * Exchange the PIN for an unlocked session.
     *
     * The PIN is compared here and never leaves the server. hash_equals keeps
     * the comparison constant-time; the route is throttled so a 7-digit PIN
     * cannot simply be walked through.
     */
    public function unlock(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|string|max:32',
        ]);

        $expected = (string) config('recyclebin.pin');

        if ($expected === '' || ! hash_equals($expected, $validated['pin'])) {
            Log::warning('Recycle bin unlock failed', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Incorrect PIN.'], 422);
        }

        $request->session()->put(EnsureRecycleBinUnlocked::SESSION_KEY, now());

        return response()->json([
            'message' => 'Recycle bin unlocked',
            'expires_in_minutes' => (int) config('recyclebin.unlock_ttl'),
        ]);
    }

    /**
     * Drop the unlock, so leaving the page re-locks it for the next visit.
     */
    public function lock(Request $request)
    {
        $request->session()->forget(EnsureRecycleBinUnlocked::SESSION_KEY);

        return response()->json(['message' => 'Recycle bin locked']);
    }

    /**
     * Counts for the tab badges. Only counts what the caller may actually see.
     */
    public function counts(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'orders' => $user->can('delete orders') ? Order::trashedForBin()->count() : 0,
            'clients' => $user->can('delete client') ? Client::onlyTrashed()->count() : 0,
            'inventory' => ($user->can('delete inventory') ? Product::onlyTrashed()->count() : 0)
                + ($user->can('delete client') ? ClientProduct::onlyTrashed()->count() : 0),
        ]);
    }

    // ---------------------------------------------------------------- orders

    public function orders(Request $request)
    {
        $query = Order::trashedForBin()->with(['client', 'items', 'deletedBy']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $this->applyTrashedSort($request, $query, ['deleted_at', 'order_number', 'total_price', 'created_at']);

        $paginator = $query->paginate($this->trashedPerPage($request));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'client_name' => $order->client?->company_name,
                'client_trashed' => (bool) $order->client?->trashed(),
                'items_count' => $order->items->count(),
                'total_price' => $order->total_price,
                'fulfillment_status' => $order->fulfillment_status,
                'deleted_at' => $order->deleted_at?->toIso8601String(),
                'deleted_by' => self::auditFields($order),
            ])->all(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function restoreOrders(Request $request)
    {
        $ids = $this->trashedIds($request);

        $restored = 0;

        DB::transaction(function () use ($ids, &$restored) {
            Order::trashedForBin()->whereIn('id', $ids)->get()->each(function (Order $order) use (&$restored) {
                $order->restore();
                $restored++;
            });
        });

        return response()->json([
            'message' => $this->restoreMessage($restored, count($ids), 'order'),
            'restored_count' => $restored,
            'requested_count' => count($ids),
        ]);
    }

    public function purgeOrders(Request $request)
    {
        $ids = $this->trashedIds($request);

        $purged = $this->purgeTrashed(Order::trashedForBin()->whereIn('id', $ids));

        return response()->json([
            'message' => "Permanently deleted {$purged} " . str('order')->plural($purged),
            'purged_count' => $purged,
        ]);
    }

    public function purgeAllOrders()
    {
        $purged = $this->purgeTrashed(Order::trashedForBin());

        return response()->json([
            'message' => "Permanently deleted {$purged} " . str('order')->plural($purged),
            'purged_count' => $purged,
        ]);
    }

    // --------------------------------------------------------------- clients

    public function clients(Request $request)
    {
        $query = Client::onlyTrashed()->with('deletedBy')->withCount([
            'orders',
            'clientProducts' => fn ($q) => $q->withTrashed(),
        ]);

        if ($search = trim((string) $request->input('search'))) {
            $query->search($search);
        }

        $this->applyTrashedSort($request, $query, ['deleted_at', 'company_name', 'created_at']);

        $paginator = $query->paginate($this->trashedPerPage($request));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Client $client) => [
                'id' => $client->id,
                'company_name' => $client->company_name,
                'short_id' => $client->short_id,
                'contact_person' => $client->contact_person,
                'status' => $client->status,
                'orders_count' => $client->orders_count,
                'products_count' => $client->client_products_count,
                'deleted_at' => $client->deleted_at?->toIso8601String(),
                'deleted_by' => self::auditFields($client),
            ])->all(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function restoreClients(Request $request)
    {
        $ids = $this->trashedIds($request);

        $restored = 0;

        DB::transaction(function () use ($ids, &$restored) {
            Client::onlyTrashed()->whereIn('id', $ids)->get()->each(function (Client $client) use (&$restored) {
                $client->restore();
                $restored++;
            });
        });

        return response()->json([
            'message' => $this->restoreMessage($restored, count($ids), 'client'),
            'restored_count' => $restored,
            'requested_count' => count($ids),
        ]);
    }

    public function purgeClients(Request $request)
    {
        $ids = $this->trashedIds($request);

        $purged = $this->purgeTrashed(Client::onlyTrashed()->whereIn('id', $ids));

        return response()->json([
            'message' => "Permanently deleted {$purged} " . str('client')->plural($purged),
            'purged_count' => $purged,
        ]);
    }

    public function purgeAllClients()
    {
        $purged = $this->purgeTrashed(Client::onlyTrashed());

        return response()->json([
            'message' => "Permanently deleted {$purged} " . str('client')->plural($purged),
            'purged_count' => $purged,
        ]);
    }

    // ------------------------------------------------------------- inventory

    /**
     * "Inventory" is two different models: the global catalog (Product) and
     * per-client stock (ClientProduct). They are listed together, as one
     * paginated table, via a UNION ALL over a normalised projection.
     *
     * Merging in PHP instead would break both the total and the page
     * boundaries, so the union is paginated in SQL and the winning rows are
     * then rehydrated into real models for their accessors.
     */
    public function inventory(Request $request)
    {
        $user = $request->user();
        $canCatalog = $user->can('delete inventory');
        $canClientStock = $user->can('delete client');

        if (! $canCatalog && ! $canClientStock) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $this->trashedPerPage($request), 'total' => 0],
            ]);
        }

        $search = trim((string) $request->input('search'));
        $parts = [];

        if ($canCatalog) {
            $catalog = DB::table('products')
                ->selectRaw("'product' as item_type, id, title as name, variant_sku as sku, handle as code, deleted_at")
                ->whereNotNull('deleted_at');

            if ($search !== '') {
                $catalog->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('variant_sku', 'like', "%{$search}%")
                        ->orWhere('handle', 'like', "%{$search}%");
                });
            }

            $parts[] = $catalog;
        }

        if ($canClientStock) {
            $stock = DB::table('client_products')
                ->selectRaw("'client_product' as item_type, id, name, sku, product_code as code, deleted_at")
                ->whereNotNull('deleted_at');

            if ($search !== '') {
                $stock->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                });
            }

            $parts[] = $stock;
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union = $union->unionAll($part);
        }

        $query = DB::query()->fromSub($union, 'i');
        $this->applyTrashedSort($request, $query, ['deleted_at', 'name', 'sku', 'code']);

        $paginator = $query->paginate($this->trashedPerPage($request));

        return response()->json([
            'data' => $this->hydrateInventoryRows(collect($paginator->items())),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    /**
     * Turn the union's flat rows back into display rows, preserving the order
     * the paginator returned them in.
     */
    private function hydrateInventoryRows($rows): array
    {
        $productIds = $rows->where('item_type', 'product')->pluck('id')->all();
        $clientProductIds = $rows->where('item_type', 'client_product')->pluck('id')->all();

        $products = $productIds === []
            ? collect()
            : Product::onlyTrashed()->with('deletedBy')->whereIn('id', $productIds)->get()->keyBy('id');

        $clientProducts = $clientProductIds === []
            ? collect()
            : ClientProduct::onlyTrashed()
                ->whereIn('id', $clientProductIds)
                ->with(['client' => fn ($q) => $q->withTrashed(), 'deletedBy'])
                ->get()
                ->keyBy('id');

        return $rows->map(function ($row) use ($products, $clientProducts) {
            if ($row->item_type === 'product') {
                $product = $products->get($row->id);

                return $product === null ? null : [
                    // id collides between the two tables, so row identity is
                    // the composite key -- see the frontend's getRowId.
                    'row_id' => "product:{$product->id}",
                    'item_type' => 'product',
                    'id' => $product->id,
                    'name' => $product->title,
                    'sku' => $product->variant_sku,
                    'code' => $product->handle,
                    'price' => $product->variant_price,
                    'quantity' => $product->variant_inventory_qty,
                    'client_name' => null,
                    'parent_trashed' => false,
                    'deleted_at' => $product->deleted_at?->toIso8601String(),
                    'deleted_by' => self::auditFields($product),
                ];
            }

            $clientProduct = $clientProducts->get($row->id);

            return $clientProduct === null ? null : [
                'row_id' => "client_product:{$clientProduct->id}",
                'item_type' => 'client_product',
                'id' => $clientProduct->id,
                'name' => $clientProduct->name,
                'sku' => $clientProduct->sku,
                'code' => $clientProduct->product_code,
                'price' => $clientProduct->unit_price,
                'quantity' => $clientProduct->quantity,
                'client_name' => $clientProduct->client?->company_name,
                // Restoring a product whose client is still in the bin would
                // leave it unreachable, so the row is flagged up front.
                'parent_trashed' => (bool) $clientProduct->client?->trashed(),
                'deleted_at' => $clientProduct->deleted_at?->toIso8601String(),
                'deleted_by' => self::auditFields($clientProduct),
            ];
        })->filter()->values()->all();
    }

    /**
     * Split composite row ids back into per-model id lists, dropping anything
     * the caller lacks the permission for. Authorisation is re-checked here
     * and not trusted from the UI.
     *
     * @return array{product: array<int, int>, client_product: array<int, int>}
     */
    private function splitInventoryIds(Request $request): array
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $user = $request->user();
        $split = ['product' => [], 'client_product' => []];

        foreach ($validated['ids'] as $composite) {
            [$type, $id] = array_pad(explode(':', $composite, 2), 2, null);

            if ($id === null || ! ctype_digit($id)) {
                continue;
            }

            if ($type === 'product' && $user->can('delete inventory')) {
                $split['product'][] = (int) $id;
            } elseif ($type === 'client_product' && $user->can('delete client')) {
                $split['client_product'][] = (int) $id;
            }
        }

        return $split;
    }

    public function restoreInventory(Request $request)
    {
        $split = $this->splitInventoryIds($request);
        $requested = count($split['product']) + count($split['client_product']);

        $restored = 0;
        $blocked = [];
        $renamed = [];

        DB::transaction(function () use ($split, &$restored, &$blocked, &$renamed) {
            Product::onlyTrashed()->whereIn('id', $split['product'])->get()
                ->each(function (Product $product) use (&$restored) {
                    $product->restore();
                    $restored++;
                });

            ClientProduct::onlyTrashed()
                ->whereIn('id', $split['client_product'])
                ->with(['client' => fn ($q) => $q->withTrashed()])
                ->get()
                ->each(function (ClientProduct $product) use (&$restored, &$blocked, &$renamed) {
                    if ($product->client === null || $product->client->trashed()) {
                        $blocked[] = [
                            'name' => $product->name,
                            'reason' => 'Its client is still in the recycle bin. Restore the client first.',
                        ];

                        return;
                    }

                    // product_code is unique table-wide, so in a healthy
                    // database a live row cannot be holding this one's code --
                    // the index would have refused the insert. Kept as a
                    // backstop for data that predates that index, where the
                    // alternative is a restore that dies on a 1062.
                    $taken = ClientProduct::withTrashed()
                        ->where('product_code', $product->product_code)
                        ->whereKeyNot($product->id)
                        ->exists();

                    if ($taken) {
                        $newCode = ClientProduct::generateProductCode($product->client);
                        $renamed[] = ['name' => $product->name, 'from' => $product->product_code, 'to' => $newCode];
                        $product->product_code = $newCode;
                    }

                    $product->restore();
                    $restored++;
                });
        });

        return response()->json([
            'message' => $this->restoreMessage($restored, $requested, 'item'),
            'restored_count' => $restored,
            'requested_count' => $requested,
            'blocked' => $blocked,
            'renamed' => $renamed,
        ]);
    }

    public function purgeInventory(Request $request)
    {
        $split = $this->splitInventoryIds($request);

        $purged = $this->purgeTrashed(Product::onlyTrashed()->whereIn('id', $split['product']))
            + $this->purgeTrashed(ClientProduct::onlyTrashed()->whereIn('id', $split['client_product']));

        return response()->json([
            'message' => "Permanently deleted {$purged} " . str('item')->plural($purged),
            'purged_count' => $purged,
        ]);
    }

    public function purgeAllInventory(Request $request)
    {
        $user = $request->user();
        $purged = 0;

        if ($user->can('delete inventory')) {
            $purged += $this->purgeTrashed(Product::onlyTrashed());
        }

        if ($user->can('delete client')) {
            $purged += $this->purgeTrashed(ClientProduct::onlyTrashed());
        }

        return response()->json([
            'message' => "Permanently deleted {$purged} " . str('item')->plural($purged),
            'purged_count' => $purged,
        ]);
    }

    /**
     * Who deleted a row, and from where.
     *
     * Null for anything deleted before the audit columns existed, so every
     * consumer has to tolerate a missing actor.
     *
     * @return array<string, string|null>|null
     */
    private static function auditFields($model): ?array
    {
        if ($model->deleted_by === null && $model->deleted_ip === null) {
            return null;
        }

        return [
            'name' => $model->deletedBy?->name,
            'email' => $model->deletedBy?->email,
            'ip' => $model->deleted_ip,
            'device' => $model->deleted_device,
            'user_agent' => $model->deleted_user_agent,
        ];
    }

    /**
     * Pagination fields, named the way the existing list endpoints name them.
     */
    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Restores can come up short when an id was already restored by someone
     * else, so report the shortfall instead of claiming a clean success.
     */
    private function restoreMessage(int $restored, int $requested, string $noun): string
    {
        $label = str($noun)->plural($restored);

        if ($restored === $requested) {
            return "Restored {$restored} {$label}";
        }

        return "Restored {$restored} of {$requested} {$label}";
    }
}
