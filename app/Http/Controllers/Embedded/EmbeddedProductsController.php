<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Read-only list of the KSA Drop catalogue for the embedded app: the products
 * merchants can sell, the same set the portal offers dropshippers
 * (published = true). Deliberately not plan-gated yet.
 *
 * Only merchant-facing fields are returned. cost_per_item is our own
 * acquisition cost and never leaves the admin side; the merchant's price is
 * variant_price (see Product::merchantCost()).
 */
class EmbeddedProductsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Product::where('published', true)->where('status', 'active');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->search($search);
        }

        $page = $query->with(['images' => fn ($q) => $q->limit(1)])
            ->orderBy('title')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $p) => [
                'id'        => $p->id,
                'title'     => $p->title,
                'sku'       => $p->variant_sku,
                'price'     => $p->merchantCost(),
                'image'     => $p->images->first()?->src ?? $p->primary_image,
                'in_stock'  => $p->variant_inventory_qty === null || $p->variant_inventory_qty > 0,
            ])->values(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
        ]);
    }
}
