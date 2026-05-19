<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('images');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('vendor') && $request->vendor !== 'all') {
            $query->where('vendor', $request->vendor);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('published') && $request->published !== 'all') {
            $query->where('published', $request->published === 'true');
        }

        if ($request->filled('min_price')) {
            $query->where('variant_price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('variant_price', '<=', $request->max_price);
        }

        if ($request->filled('low_stock')) {
            $query->where('variant_inventory_qty', '<=', (int) $request->low_stock);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    public function show(Product $product)
    {
        $product->load('images');
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,draft,archived',
            'variant_price' => 'sometimes|numeric|min:0',
            'variant_compare_at_price' => 'sometimes|nullable|numeric|min:0',
            'variant_inventory_qty' => 'sometimes|integer',
            'tags' => 'sometimes|array',
            'published' => 'sometimes|boolean',
        ]);

        $product->update($validated);
        $product->load('images');

        return response()->json($product);
    }

    public function statistics()
    {
        $total = Product::count();
        $active = Product::where('status', 'active')->count();
        $draft = Product::where('status', 'draft')->count();
        $archived = Product::where('status', 'archived')->count();
        $outOfStock = Product::where('variant_inventory_qty', 0)->count();
        $lowStock = Product::where('variant_inventory_qty', '>', 0)
            ->where('variant_inventory_qty', '<=', 5)->count();
        $totalValue = (float) Product::sum(DB::raw('variant_price * variant_inventory_qty'));

        return response()->json([
            'total_products' => $total,
            'active_products' => $active,
            'draft_products' => $draft,
            'archived_products' => $archived,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'total_inventory_value' => round($totalValue, 2),
        ]);
    }

    public function filterOptions()
    {
        return response()->json([
            'statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
            'vendors' => DB::table('products')
                ->select('vendor')
                ->distinct()
                ->whereNotNull('vendor')
                ->whereNull('deleted_at')
                ->orderBy('vendor')
                ->pluck('vendor')
                ->map(fn ($v) => ['value' => $v, 'label' => $v])
                ->prepend(['value' => 'all', 'label' => 'All Vendors'])
                ->values(),
            'types' => DB::table('products')
                ->select('type')
                ->distinct()
                ->whereNotNull('type')
                ->whereNull('deleted_at')
                ->orderBy('type')
                ->pluck('type')
                ->map(fn ($t) => ['value' => $t, 'label' => $t])
                ->prepend(['value' => 'all', 'label' => 'All Types'])
                ->values(),
        ]);
    }

    public function export(Request $request)
    {
        $query = Product::with('images');

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('vendor') && $request->vendor !== 'all') {
            $query->where('vendor', $request->vendor);
        }

        $products = $query->get();

        $filename = 'products_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Handle', 'Title', 'Body (HTML)', 'Vendor', 'Product Category', 'Type', 'Tags', 'Published',
                'Option1 Name', 'Option1 Value', 'Option2 Name', 'Option2 Value', 'Option3 Name', 'Option3 Value',
                'Variant SKU', 'Variant Grams', 'Variant Inventory Qty', 'Variant Inventory Policy',
                'Variant Fulfillment Service', 'Variant Price', 'Variant Compare At Price',
                'Variant Requires Shipping', 'Variant Taxable', 'Variant Barcode',
                'Image Src', 'Image Position', 'Image Alt Text',
                'SEO Title', 'SEO Description',
                'Cost per item', 'Status',
                'Included / Saudi Arabia', 'Price / Saudi Arabia', 'Compare At Price / Saudi Arabia',
                'Included / Pakistan', 'Price / Pakistan', 'Compare At Price / Pakistan',
            ]);

            foreach ($products as $product) {
                $firstImage = $product->images->first();
                fputcsv($file, [
                    $product->handle,
                    $product->title,
                    $product->body_html,
                    $product->vendor,
                    $product->product_category,
                    $product->type,
                    is_array($product->tags) ? implode(', ', $product->tags) : ($product->tags ?? ''),
                    $product->published ? 'true' : 'false',
                    $product->option1_name,
                    $product->option1_value,
                    $product->option2_name,
                    $product->option2_value,
                    $product->option3_name,
                    $product->option3_value,
                    $product->variant_sku,
                    $product->variant_grams,
                    $product->variant_inventory_qty,
                    $product->variant_inventory_policy,
                    $product->variant_fulfillment_service,
                    $product->variant_price,
                    $product->variant_compare_at_price,
                    $product->variant_requires_shipping ? 'true' : 'false',
                    $product->variant_taxable ? 'true' : 'false',
                    $product->variant_barcode,
                    $firstImage?->src ?? '',
                    $firstImage?->position ?? '',
                    $firstImage?->alt_text ?? '',
                    $product->seo_title,
                    $product->seo_description,
                    $product->cost_per_item,
                    $product->status,
                    $product->included_saudi_arabia ? 'true' : 'false',
                    $product->price_saudi_arabia,
                    $product->compare_at_price_saudi_arabia,
                    $product->included_pakistan ? 'true' : 'false',
                    $product->price_pakistan,
                    $product->compare_at_price_pakistan,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return response()->json(['success' => false, 'message' => 'Unable to open CSV file'], 422);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Invalid CSV — no headers'], 422);
        }

        $imported = 0;
        $updated = 0;
        $errors = [];
        $invalidRows = 0;
        $totalRows = 0;

        // Group rows by handle so we can attach multiple images per product
        $productRows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            if (empty(array_filter($row))) { $invalidRows++; continue; }
            $data = array_combine(array_slice($headers, 0, count($row)), $row);
            $handle_val = trim($data['Handle'] ?? '');
            if (!$handle_val) { $invalidRows++; continue; }
            if (!isset($productRows[$handle_val])) {
                $productRows[$handle_val] = ['data' => $data, 'images' => []];
            }
            if (!empty($data['Image Src'])) {
                $productRows[$handle_val]['images'][] = [
                    'src' => trim($data['Image Src']),
                    'position' => (int) ($data['Image Position'] ?? count($productRows[$handle_val]['images']) + 1),
                    'alt_text' => $data['Image Alt Text'] ?? null,
                ];
            }
        }
        fclose($handle);

        DB::beginTransaction();
        try {
            foreach ($productRows as $handle_val => $entry) {
                $data = $entry['data'];
                try {
                    $tags = !empty($data['Tags'])
                        ? array_map('trim', explode(',', $data['Tags']))
                        : [];

                    $payload = [
                        'handle' => $handle_val,
                        'title' => $data['Title'] ?? $handle_val,
                        'body_html' => $data['Body (HTML)'] ?? null,
                        'vendor' => $data['Vendor'] ?? null,
                        'product_category' => $data['Product Category'] ?? null,
                        'type' => $data['Type'] ?? null,
                        'tags' => $tags,
                        'published' => strtolower($data['Published'] ?? 'true') === 'true',
                        'option1_name' => $data['Option1 Name'] ?? null,
                        'option1_value' => $data['Option1 Value'] ?? null,
                        'option2_name' => $data['Option2 Name'] ?? null,
                        'option2_value' => $data['Option2 Value'] ?? null,
                        'option3_name' => $data['Option3 Name'] ?? null,
                        'option3_value' => $data['Option3 Value'] ?? null,
                        'variant_sku' => $data['Variant SKU'] ?? null,
                        'variant_grams' => !empty($data['Variant Grams']) ? (float) $data['Variant Grams'] : null,
                        'variant_inventory_qty' => (int) ($data['Variant Inventory Qty'] ?? 0),
                        'variant_inventory_policy' => $data['Variant Inventory Policy'] ?? 'deny',
                        'variant_fulfillment_service' => $data['Variant Fulfillment Service'] ?? null,
                        'variant_price' => !empty($data['Variant Price']) ? (float) $data['Variant Price'] : null,
                        'variant_compare_at_price' => !empty($data['Variant Compare At Price']) ? (float) $data['Variant Compare At Price'] : null,
                        'variant_requires_shipping' => strtolower($data['Variant Requires Shipping'] ?? 'true') === 'true',
                        'variant_taxable' => strtolower($data['Variant Taxable'] ?? 'true') === 'true',
                        'variant_barcode' => $data['Variant Barcode'] ?? null,
                        'seo_title' => $data['SEO Title'] ?? null,
                        'seo_description' => $data['SEO Description'] ?? null,
                        'cost_per_item' => !empty($data['Cost per item']) ? (float) $data['Cost per item'] : null,
                        'status' => strtolower($data['Status'] ?? 'active'),
                        'included_saudi_arabia' => strtolower($data['Included / Saudi Arabia'] ?? 'true') === 'true',
                        'price_saudi_arabia' => !empty($data['Price / Saudi Arabia']) ? (float) $data['Price / Saudi Arabia'] : null,
                        'compare_at_price_saudi_arabia' => !empty($data['Compare At Price / Saudi Arabia']) ? (float) $data['Compare At Price / Saudi Arabia'] : null,
                        'included_pakistan' => strtolower($data['Included / Pakistan'] ?? 'false') === 'true',
                        'price_pakistan' => !empty($data['Price / Pakistan']) ? (float) $data['Price / Pakistan'] : null,
                        'compare_at_price_pakistan' => !empty($data['Compare At Price / Pakistan']) ? (float) $data['Compare At Price / Pakistan'] : null,
                        'primary_image' => !empty($entry['images']) ? $entry['images'][0]['src'] : null,
                    ];

                    $existing = Product::withTrashed()->where('handle', $handle_val)->first();

                    if ($existing) {
                        $existing->restore();
                        $existing->update($payload);
                        $existing->images()->delete();
                        foreach ($entry['images'] as $img) {
                            $existing->images()->create($img);
                        }
                        $updated++;
                    } else {
                        $product = Product::create($payload);
                        foreach ($entry['images'] as $img) {
                            $product->images()->create($img);
                        }
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $invalidRows++;
                    $errors[] = ['handle' => $handle_val, 'reason' => $e->getMessage()];
                }
            }

            DB::commit();

            $parts = [];
            if ($imported) $parts[] = "{$imported} imported";
            if ($updated) $parts[] = "{$updated} updated";
            if ($invalidRows) $parts[] = "{$invalidRows} skipped";

            return response()->json([
                'success' => true,
                'message' => 'Import completed: ' . implode(', ', $parts),
                'imported' => $imported,
                'updated' => $updated,
                'invalid' => $invalidRows,
                'total' => $totalRows,
                'errors' => $errors,
                'has_errors' => !empty($errors),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 422);
        }
    }
}
