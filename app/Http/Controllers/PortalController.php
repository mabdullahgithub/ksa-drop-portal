<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\ClientProductImage;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\ProductSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    private function generateOrderNumber(string $prefix): string
    {
        // Count existing orders with this prefix to derive the next sequential number.
        // Keeps IDs short and human-readable: e.g. ASCXAS00001, ASCXAS00002 …
        $count = \App\Models\Order::where('order_number', 'like', $prefix . '%')->count();
        $next  = $count + 1;

        $candidate = $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);

        // Resolve collisions (concurrent requests, gaps from deletions, etc.)
        while (\App\Models\Order::where('order_number', $candidate)->exists()) {
            $next++;
            $candidate = $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    private function resolveClient(): ?Client
    {
        if (session()->has('impersonate.client_id')) {
            return Client::find(session('impersonate.client_id'));
        }

        return auth()->user()->client ?? null;
    }

    private function parseMultiValue($value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(
            array_map('trim', $values),
            fn ($v) => $v !== '' && $v !== 'all'
        ));
    }

    /**
     * Apply the orders-list filters to a query. Shared by the paginated list and
     * the CSV export so an export always covers exactly what the table shows.
     */
    private function applyOrderFilters($query, Request $request): void
    {
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('fulfillment_status')) {
            $values = $this->parseMultiValue($request->fulfillment_status);
            if (!empty($values)) {
                $query->whereIn('fulfillment_status', $values);
            }
        }

        if ($request->has('financial_status')) {
            $values = $this->parseMultiValue($request->financial_status);
            if (!empty($values)) {
                $query->whereIn('financial_status', $values);
            }
        }

        // Filter by a single tag (used by the "Confirmed Orders" tab)
        if ($request->has('tag') && $request->tag) {
            $query->whereJsonContains('tags', $request->tag);
        }

        // Filter by multiple tags (multi-select "Tags" filter). An order matches
        // when it carries any of the selected tags.
        if ($request->has('tags')) {
            $tags = $this->parseMultiValue($request->tags);
            if (!empty($tags)) {
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }
        }

        // Filter by shipment status (has shipment or not)
        if ($request->has('has_shipment')) {
            $hasShipment = filter_var($request->has_shipment, FILTER_VALIDATE_BOOLEAN);
            if ($hasShipment) {
                $query->withShipment();
            } else {
                $query->withoutShipment();
            }
        }

        // Filter by shipment status values
        if ($request->has('shipment_status')) {
            $statuses = $this->parseMultiValue($request->shipment_status);
            if (!empty($statuses)) {
                $query->whereHas('shipments', function ($q) use ($statuses) {
                    $q->whereIn('status', $statuses);
                });
            }
        }
    }

    public function dashboard()
    {
        $client = $this->resolveClient();

        if (!$client) {
            abort(403);
        }

        $ordersBase = $client->orders();

        $stats = [
            'total_orders'        => $client->orders()->count(),
            'total_revenue'       => round((float) $client->orders()->where('financial_status', 'paid')->sum('total'), 2),
            'pending_orders'      => $client->orders()->where('fulfillment_status', 'pending')->count(),
            'confirmed_orders'    => $client->orders()->whereJsonContains('tags', 'confirmed')->count(),
            'fulfilled_orders'    => $client->orders()->where('fulfillment_status', 'fulfilled')->count(),
            'cancelled_orders'    => $client->orders()->where('fulfillment_status', 'cancelled')->count(),
            'unfulfilled_orders'  => $client->orders()->where('fulfillment_status', 'unfulfilled')->count(),
            'total_products'      => $client->clientProducts()->count(),
            'verified_products'   => $client->clientProducts()->verified()->count(),
            'pending_verification'=> $client->clientProducts()->pending()->count(),
            'unassigned_orders'   => $client->orders()->withoutShipment()->count(),
            'assigned_orders'     => $client->orders()->withShipment()->count(),
            'by_shipment_status'  => DB::table('shipments')
                ->whereIn('order_id', $client->orders()->pluck('id'))
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->orderByDesc('count')
                ->get(),
            'by_tag' => Tag::orderBy('created_at')->get(['id', 'name', 'color'])->map(fn ($tag) => [
                'id'    => $tag->id,
                'name'  => $tag->name,
                'color' => $tag->color,
                'count' => $client->orders()->whereJsonContains('tags', $tag->name)->count(),
            ]),
        ];

        $recentOrders = $client->orders()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'order_number', 'customer_name', 'total', 'fulfillment_status', 'financial_status', 'created_at']);

        return response()->json([
            'stats' => $stats,
            'recent_orders' => $recentOrders,
            'client' => [
                'company_name' => $client->company_name,
                'client_id' => $client->client_id,
                'type_label' => $client->type_label,
                'portal_features' => $client->portal_features,
            ],
        ]);
    }

    public function orders(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $query = $client->orders()->with(['items', 'latestShipment', 'invoices']);

        $this->applyOrderFilters($query, $request);

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function showOrder(int $orderId)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $order = $client->orders()
            ->with(['items.clientProduct', 'items.product', 'latestShipment', 'invoices'])
            ->findOrFail($orderId);

        return response()->json($order);
    }

    public function importOrders(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $request->validate([
            'file'        => 'required|file|mimes:csv,txt|max:51200',
            'import_note' => 'nullable|string|max:2000',
        ]);

        $importNote = trim($request->input('import_note', ''));

        $file = $request->file('file');
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return response()->json(['success' => false, 'message' => 'Unable to open CSV file'], 422);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Invalid CSV format - no headers found'], 422);
        }

        $imported = 0;
        $errors = [];
        $duplicates = 0;
        $invalidRows = 0;
        $totalRows = 0;

        // Always auto-generate order numbers prefixed with the client identifier
        // so they stay unique across clients (order_number has a global unique index).
        // Format: "{prefix}{5-digit-seq}" e.g. "EVENIE00001".
        $prefix = $client->short_id ?: 'CL' . $client->id;

        // Resolve the default "Pending" tag once so every imported order gets it.
        $defaultTags = $this->defaultOrderTags();

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $totalRows++;

                try {
                    if (empty(array_filter($row))) {
                        $invalidRows++;
                        continue;
                    }

                    $headerCount = count($headers);
                    $rowCount = count($row);
                    if ($rowCount < $headerCount) {
                        $row = array_pad($row, $headerCount, '');
                    } elseif ($rowCount > $headerCount) {
                        $row = array_slice($row, 0, $headerCount);
                    }

                    $data = array_combine($headers, $row);

                    // ── Resolve customer name ────────────────────────────────────
                    // Simplified template uses "Name" for the customer name.
                    // Shopify exports use "Name" for the ORDER name (e.g. "#1082")
                    // and put the customer's name in "Billing Name" / "Shipping Name".
                    // So whenever those Shopify columns are present, prefer them and
                    // ignore "Name" — otherwise "#1082" ends up as the customer name.
                    $hasShopifyNameColumns = array_key_exists('Billing Name', $data)
                        || array_key_exists('Shipping Name', $data);
                    $customerName = $hasShopifyNameColumns
                        ? trim((string) ($data['Billing Name'] ?? $data['Shipping Name'] ?? ''))
                        : trim((string) ($data['Name'] ?? ''));

                    // ── Resolve phone ────────────────────────────────────────────
                    $customerPhone = trim(
                        (string) ($data['Phone'] ?? $data['Billing Phone'] ?? $data['Shipping Phone'] ?? '')
                    );

                    if ($customerName === '') {
                        $invalidRows++;
                        $errors[] = ['row' => $totalRows, 'reason' => 'Missing customer name', 'details' => 'Name column is required'];
                        continue;
                    }

                    if ($customerPhone === '') {
                        $invalidRows++;
                        $errors[] = ['row' => $totalRows, 'reason' => 'Missing phone', 'details' => 'Phone column is required'];
                        continue;
                    }

                    // ── Auto-generate a unique order number ──────────────────────
                    $orderNumber = $this->generateOrderNumber($prefix);

                    // ── Resolve address fields ───────────────────────────────────
                    // Simplified template: Address, City
                    // Shopify format:      Shipping Address1, Shipping City, …
                    $shippingAddress1 = trim(
                        (string) ($data['Address'] ?? $data['Shipping Address1'] ?? $data['Billing Address1'] ?? '')
                    ) ?: null;
                    $shippingCity = trim(
                        (string) ($data['City'] ?? $data['Shipping City'] ?? $data['Billing City'] ?? '')
                    ) ?: null;
                    $shippingCountry = trim(
                        (string) ($data['Shipping Country'] ?? $data['Billing Country'] ?? '')
                    ) ?: null;

                    // ── Resolve COD / selling price ──────────────────────────────
                    // Simplified template: COD
                    // Shopify format:      Total
                    $total = floatval($data['COD'] ?? $data['Total'] ?? 0);

                    // ── Resolve product fields ───────────────────────────────────
                    // Simplified template: Product Name, SKU
                    // Shopify format:      Lineitem name, Lineitem sku
                    $lineitemName = trim(
                        (string) ($data['Product Name'] ?? $data['Lineitem name'] ?? '')
                    ) ?: null;
                    $lineitemSku = trim(
                        (string) ($data['SKU'] ?? $data['Lineitem sku'] ?? '')
                    ) ?: null;
                    $lineitemQty  = intval($data['Lineitem quantity'] ?? 1);
                    $lineitemPrice = floatval($data['Lineitem price'] ?? ($data['COD'] ?? 0));

                    // ── Timestamps ───────────────────────────────────────────────
                    $createdAt = now();
                    if (!empty($data['Created at'])) {
                        try { $createdAt = date('Y-m-d H:i:s', strtotime($data['Created at'])); } catch (\Exception $e) {}
                    }
                    $paidAt = null;
                    if (!empty($data['Paid at'])) {
                        try { $paidAt = date('Y-m-d H:i:s', strtotime($data['Paid at'])); } catch (\Exception $e) {}
                    }
                    $fulfilledAt = null;
                    if (!empty($data['Fulfilled at'])) {
                        try { $fulfilledAt = date('Y-m-d H:i:s', strtotime($data['Fulfilled at'])); } catch (\Exception $e) {}
                    }
                    $cancelledAt = null;
                    if (!empty($data['Cancelled at'])) {
                        try { $cancelledAt = date('Y-m-d H:i:s', strtotime($data['Cancelled at'])); } catch (\Exception $e) {}
                    }

                    // ── Notes: merge import note + optional CSV notes ─────────────
                    $csvNotes = trim((string) ($data['Notes'] ?? ''));
                    $notes = $importNote !== ''
                        ? ($importNote . ($csvNotes !== '' ? "\n" . $csvNotes : ''))
                        : ($csvNotes ?: null);

                    $order = \App\Models\Order::create([
                        'client_id'          => $client->id,
                        'order_number'       => $orderNumber,
                        'customer_name'      => $customerName,
                        'customer_email'     => $data['Email'] ?? null,
                        'customer_phone'     => $customerPhone,
                        'financial_status'   => strtolower($data['Financial Status'] ?? 'pending'),
                        'fulfillment_status' => strtolower($data['Fulfillment Status'] ?? 'unfulfilled'),
                        'accepts_marketing'  => strtolower($data['Accepts Marketing'] ?? 'no') === 'yes',
                        'currency'           => $data['Currency'] ?? 'SAR',
                        'subtotal'           => $total,
                        'total'              => $total,
                        'shipping_cost'      => floatval($data['Shipping'] ?? 0),
                        'taxes'              => floatval($data['Taxes'] ?? 0),
                        'discount_code'      => $data['Discount Code'] ?? null,
                        'discount_amount'    => floatval($data['Discount Amount'] ?? 0),
                        'shipping_method'    => $data['Shipping Method'] ?? null,
                        'billing_name'       => $customerName,
                        'billing_phone'      => $customerPhone,
                        'shipping_name'      => $customerName,
                        'shipping_address1'  => $shippingAddress1,
                        'shipping_city'      => $shippingCity,
                        'shipping_country'   => $shippingCountry,
                        'shipping_phone'     => $customerPhone,
                        'payment_method'     => $data['Payment Method'] ?? 'Cash on Delivery',
                        'notes'              => $notes,
                        'tags'               => $defaultTags,
                        'risk_level'         => $data['Risk Level'] ?? 'Low',
                        'source'             => $data['Source'] ?? null,
                        'paid_at'            => $paidAt,
                        'fulfilled_at'       => $fulfilledAt,
                        'cancelled_at'       => $cancelledAt,
                        'created_at'         => $createdAt,
                    ]);

                    if ($lineitemName) {
                        $order->items()->create([
                            'lineitem_quantity'           => $lineitemQty,
                            'lineitem_name'               => $lineitemName,
                            'lineitem_price'              => $lineitemPrice,
                            'lineitem_sku'                => $lineitemSku,
                            'lineitem_requires_shipping'  => true,
                            'lineitem_taxable'            => false,
                            'lineitem_fulfillment_status' => 'unfulfilled',
                        ]);
                    }

                    $imported++;
                } catch (\Exception $rowError) {
                    $invalidRows++;
                    $errors[] = ['row' => $totalRows, 'reason' => 'Processing error', 'details' => $rowError->getMessage()];
                    continue;
                }
            }

            fclose($handle);
            DB::commit();

            $summary = [];
            if ($imported > 0) $summary[] = "{$imported} order(s) imported";
            if ($duplicates > 0) $summary[] = "{$duplicates} duplicate(s) skipped";
            if ($invalidRows > 0) $summary[] = "{$invalidRows} invalid row(s) skipped";

            $message = 'Import completed successfully' . (!empty($summary) ? ': ' . implode(', ', $summary) : '');

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'duplicates' => $duplicates,
                'invalid' => $invalidRows,
                'total' => $totalRows,
                'errors' => $errors,
                'has_errors' => !empty($errors),
            ]);
        } catch (\Exception $e) {
            fclose($handle);
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage(), 'errors' => $errors], 422);
        }
    }

    /**
     * Default tag applied to every order created on the platform (manual entry
     * or CSV import). Resolves the actual "Pending" tag from the tags catalog
     * (creating it if it doesn't exist yet) and returns its name, since an
     * order's `tags` column stores catalog tag names.
     */
    private function defaultOrderTags(): array
    {
        $pending = Tag::firstOrCreate(
            ['name' => 'Pending'],
            ['color' => '#f59e0b', 'description' => 'Order awaiting processing']
        );

        return [$pending->name];
    }

    public function storeOrder(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_name'      => 'required|string|max:255',
            'customer_phone'     => 'required|string|max:50',
            'customer_email'     => 'nullable|email|max:255',
            'notes'              => 'nullable|string|max:5000',
            'shipping_name'      => 'nullable|string|max:255',
            'shipping_address1'  => 'nullable|string|max:500',
            'shipping_city'      => 'nullable|string|max:255',
            'shipping_country'   => 'nullable|string|max:2',
            'shipping_phone'     => 'nullable|string|max:50',
            'total'              => 'nullable|numeric|min:0',
            'currency'           => 'nullable|string|max:3',
            'payment_method'     => 'nullable|string|max:255',
            'lineitem_name'              => 'nullable|string|max:500',
            'lineitem_quantity'          => 'nullable|integer|min:1',
            'lineitem_sku'               => 'nullable|string|max:255',
            'lineitem_client_product_id' => 'nullable|integer|exists:client_products,id',
            'lineitem_product_id'        => 'nullable|integer|exists:products,id',
        ]);

        $prefix      = $client->short_id ?: 'CL' . $client->id;
        $orderNumber = $this->generateOrderNumber($prefix);

        $order = \App\Models\Order::create([
            'client_id'       => $client->id,
            'order_number'    => $orderNumber,
            'customer_name'   => $validated['customer_name'],
            'customer_phone'  => $validated['customer_phone'],
            'customer_email'  => $validated['customer_email'] ?? null,
            'notes'           => $validated['notes'] ?? null,
            'shipping_name'   => $validated['shipping_name'] ?? $validated['customer_name'],
            'shipping_address1' => $validated['shipping_address1'] ?? null,
            'shipping_city'   => $validated['shipping_city'] ?? null,
            'shipping_country'=> $validated['shipping_country'] ?? null,
            'shipping_phone'  => $validated['shipping_phone'] ?? $validated['customer_phone'],
            'total'           => $validated['total'] ?? 0,
            'subtotal'        => $validated['total'] ?? 0,
            'currency'        => $validated['currency'] ?? 'SAR',
            'payment_method'  => $validated['payment_method'] ?? null,
            'financial_status'   => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'tags'            => $this->defaultOrderTags(),
        ]);

        if (!empty($validated['lineitem_name'])) {
            $qty   = max((int) ($validated['lineitem_quantity'] ?? 1), 1);
            $total = (float) ($validated['total'] ?? 0);
            // Derive unit price from the order total the client entered
            $unitPrice = $qty > 0 ? round($total / $qty, 2) : 0;

            $order->items()->create([
                'lineitem_name'               => $validated['lineitem_name'],
                'lineitem_quantity'           => $qty,
                'lineitem_price'              => $unitPrice,
                'lineitem_sku'                => $validated['lineitem_sku'] ?? null,
                'lineitem_requires_shipping'  => true,
                'lineitem_taxable'            => false,
                'lineitem_fulfillment_status' => 'unfulfilled',
                'client_product_id'           => $validated['lineitem_client_product_id'] ?? null,
                'product_id'                  => $validated['lineitem_product_id'] ?? null,
            ]);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Order created successfully.',
            'order_number' => $orderNumber,
        ]);
    }

    public function ordersImportTemplate(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        // Simplified client-facing columns.
        // Order ID is always auto-generated — clients do NOT need to fill it in.
        $columns = [
            'Name',         // Customer full name (required)
            'Phone',        // Customer phone (required)
            'City',         // Shipping city (optional)
            'Address',      // Shipping street address (optional)
            'COD',          // Selling price / Cash-on-Delivery amount (optional)
            'Product Name', // Product / item name (optional)
            'SKU',          // Product SKU (optional)
            'Notes',        // Any order notes or description (optional)
        ];

        // Two sample rows to show the expected format.
        $samples = [
            ['John Doe',        '+966500000000', 'Riyadh', '123 King Fahd Rd', '250.00', 'Blue T-Shirt / L', 'TSHIRT-BL-L', 'Handle with care'],
            ['Sarah Al-Ahmed',  '+966501111111', 'Jeddah', '45 Olaya St',      '180.00', 'Running Shoes',    'SHOE-WHT-42', ''],
        ];

        $filename = 'orders_import_template.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($columns, $samples) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($samples as $sample) {
                fputcsv($file, $sample);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportOrders(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $query = $client->orders()->with('items');

        // Explicit order ids (row selection) win over the rest of the filters.
        // Still scoped to the client's own orders, so ids from elsewhere match nothing.
        if ($request->has('order_ids')) {
            $orderIds = array_filter(array_map('intval', $this->parseMultiValue($request->order_ids)));

            $orders = $query->whereIn('id', $orderIds)->get();

            return $this->streamPortalOrdersCsv($orders);
        }

        $this->applyOrderFilters($query, $request);

        return $this->streamPortalOrdersCsv($query->get());
    }

    /**
     * Stream a collection of the client's orders as a downloadable CSV,
     * using the same Shopify-style column set as the admin orders export.
     */
    private function streamPortalOrdersCsv($orders)
    {
        $filename = 'orders_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Name', 'Email', 'Financial Status', 'Paid at', 'Fulfillment Status', 'Fulfilled at',
                'Accepts Marketing', 'Currency', 'Subtotal', 'Shipping', 'Taxes', 'Total',
                'Discount Code', 'Discount Amount', 'Shipping Method', 'Created at',
                'Lineitem quantity', 'Lineitem name', 'Lineitem price', 'Lineitem compare at price',
                'Lineitem sku', 'Lineitem requires shipping', 'Lineitem taxable', 'Lineitem fulfillment status',
                'Billing Name', 'Billing Street', 'Billing Address1', 'Billing Address2', 'Billing Company',
                'Billing City', 'Billing Zip', 'Billing Province', 'Billing Country', 'Billing Phone',
                'Shipping Name', 'Shipping Street', 'Shipping Address1', 'Shipping Address2', 'Shipping Company',
                'Shipping City', 'Shipping Zip', 'Shipping Province', 'Shipping Country', 'Shipping Phone',
                'Notes', 'Note Attributes', 'Cancelled at', 'Payment Method', 'Payment Reference',
                'Refunded Amount', 'Vendor', 'Outstanding Balance', 'Employee', 'Location', 'Device ID',
                'Id', 'Tags', 'Risk Level', 'Source',
            ]);

            foreach ($orders as $order) {
                $firstItem = $order->items->first();
                fputcsv($file, [
                    $order->order_number, $order->customer_email, $order->financial_status, $order->paid_at,
                    $order->fulfillment_status, $order->fulfilled_at, $order->accepts_marketing ? 'yes' : 'no',
                    $order->currency, $order->subtotal, $order->shipping_cost, $order->taxes, $order->total,
                    $order->discount_code, $order->discount_amount, $order->shipping_method, $order->created_at,
                    $firstItem?->lineitem_quantity ?? '', $firstItem?->lineitem_name ?? '',
                    $firstItem?->lineitem_price ?? '', $firstItem?->lineitem_compare_at_price ?? '',
                    $firstItem?->lineitem_sku ?? '', $firstItem?->lineitem_requires_shipping ? 'true' : 'false',
                    $firstItem?->lineitem_taxable ? 'true' : 'false', $firstItem?->lineitem_fulfillment_status ?? '',
                    $order->billing_name, $order->billing_street, $order->billing_address1, $order->billing_address2,
                    $order->billing_company, $order->billing_city, $order->billing_zip, $order->billing_province,
                    $order->billing_country, $order->billing_phone,
                    $order->shipping_name, $order->shipping_street, $order->shipping_address1, $order->shipping_address2,
                    $order->shipping_company, $order->shipping_city, $order->shipping_zip, $order->shipping_province,
                    $order->shipping_country, $order->shipping_phone,
                    $order->notes, is_array($order->note_attributes) ? json_encode($order->note_attributes) : ($order->note_attributes ?? ''),
                    $order->cancelled_at, $order->payment_method, $order->payment_reference, $order->refunded_amount,
                    $order->vendor, $order->outstanding_balance, $order->employee, $order->location, $order->device_id,
                    $order->id, is_array($order->tags) ? implode(', ', $order->tags) : ($order->tags ?? ''),
                    $order->risk_level, $order->source,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Lightweight SKU list for the "create order" dropdown.
     * Returns only verified client-inventory products (fulfilment clients)
     * or published catalogue products (dropshippers).
     */
    public function skuSearch(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $search = trim($request->get('search', ''));

        if ($client->is_dropshipper) {
            $query = \App\Models\Product::where('published', true)
                ->select('id', 'title as name', 'variant_sku as sku', 'variant_price as unit_price');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('variant_sku', 'like', "%{$search}%");
                });
            }

            $items = $query->orderBy('title')->limit(50)->get()
                ->map(fn ($p) => [
                    'id'           => $p->id,
                    'type'         => 'product',
                    'name'         => $p->name,
                    'sku'          => $p->sku,
                    'unit_price'   => (float) $p->unit_price,
                ]);
        } else {
            $query = $client->clientProducts()
                ->where('verification_status', 'verified')
                ->select('id', 'name', 'sku', 'unit_price');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $items = $query->orderBy('name')->limit(50)->get()
                ->map(fn ($p) => [
                    'id'           => $p->id,
                    'type'         => 'client_product',
                    'name'         => $p->name,
                    'sku'          => $p->sku,
                    'unit_price'   => (float) $p->unit_price,
                ]);
        }

        return response()->json(['items' => $items]);
    }

    public function inventory(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('inventory', $client->portal_features ?? [])) {
            abort(403);
        }

        $query = $client->clientProducts();

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('verification_status') && $request->verification_status !== 'all') {
            if ($request->verification_status === 'out_of_stock') {
                $query->where('is_out_of_stock', true);
            } else {
                $query->where('verification_status', $request->verification_status);
            }
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->load('images');

        return response()->json($paginator);
    }

    public function products(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !$client->is_dropshipper) {
            abort(403);
        }

        $query = \App\Models\Product::where('published', true);

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('vendor') && $request->vendor) {
            $query->where('vendor', $request->vendor);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function productShow(Request $request, \App\Models\Product $product)
    {
        $client = $this->resolveClient();

        if (!$client || !$client->is_dropshipper) {
            abort(403);
        }

        if (!$product->published) {
            abort(404);
        }

        $product->load('images');

        return response()->json($product);
    }

    public function productFilterOptions(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !$client->is_dropshipper) {
            abort(403);
        }

        $base = \App\Models\Product::where('published', true);

        $vendors = (clone $base)
            ->select('vendor')
            ->distinct()
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->orderBy('vendor')
            ->pluck('vendor')
            ->map(fn($v) => ['value' => $v, 'label' => $v]);

        $types = (clone $base)
            ->select('type')
            ->distinct()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->orderBy('type')
            ->pluck('type')
            ->map(fn($t) => ['value' => $t, 'label' => $t]);

        return response()->json([
            'vendors' => $vendors,
            'types'   => $types,
        ]);
    }

    public function revenue(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('revenue', $client->portal_features ?? [])) {
            abort(403);
        }

        $period = $request->get('period', '6months');

        $months = match ($period) {
            '3months' => 3,
            '6months' => 6,
            '12months' => 12,
            default => 6,
        };

        $monthlyRevenue = $client->orders()
            ->where('financial_status', 'paid')
            ->where('created_at', '>=', now()->subMonths($months))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total) as revenue, COUNT(*) as orders_count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $totalRevenue = round((float) $client->orders()->where('financial_status', 'paid')->sum('total'), 2);
        $thisMonthRevenue = round((float) $client->orders()
            ->where('financial_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total'), 2);

        $commissionRate = $client->commission_rate;
        $totalCommission = $commissionRate ? round($totalRevenue * ($commissionRate / 100), 2) : null;

        return response()->json([
            'total_revenue' => $totalRevenue,
            'this_month_revenue' => $thisMonthRevenue,
            'commission_rate' => $commissionRate,
            'total_commission' => $totalCommission,
            'monthly_breakdown' => $monthlyRevenue,
        ]);
    }

    public function finance(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('finance', $client->portal_features ?? [])) {
            abort(403);
        }

        $period = $request->get('period', '6months');
        $months = match ($period) {
            '3months' => 3,
            '6months' => 6,
            '12months' => 12,
            default => 6,
        };

        $startDate = now()->subMonths($months);

        $charges = $client->charges ?? [];
        $deliveryFee    = (float) ($charges['delivery'] ?? 0);
        $callConfirmFee = (float) ($charges['call_confirmation'] ?? 0);
        $codFee         = (float) ($charges['cod'] ?? 0);
        $otherFee       = (float) ($charges['other'] ?? 0);
        $vatPercent     = (float) ($charges['vat'] ?? 0);
        $isDropshipper  = $client->is_dropshipper;

        $orders = $client->orders()
            ->where('created_at', '>=', $startDate)
            ->with(['items:id,order_id,lineitem_sku,lineitem_quantity'])
            ->get(['id', 'total', 'created_at']);

        // Pre-load product costs keyed by SKU (only needed for dropshippers)
        $productCostBySku = [];
        if ($isDropshipper) {
            $allSkus = $orders
                ->flatMap(fn($o) => $o->items->pluck('lineitem_sku'))
                ->filter()
                ->unique()
                ->values();

            if ($allSkus->isNotEmpty()) {
                \App\Models\Product::whereIn('variant_sku', $allSkus)
                    ->get(['variant_sku', 'price_saudi_arabia', 'variant_price'])
                    ->each(function ($p) use (&$productCostBySku) {
                        $productCostBySku[$p->variant_sku] =
                            (float) ($p->price_saudi_arabia ?? $p->variant_price ?? 0);
                    });
            }
        }

        $totalSoldValue  = 0.0;
        $totalProfit     = 0.0;
        $monthlyData     = [];

        foreach ($orders as $order) {
            $revenue = (float) $order->total;
            $totalSoldValue += $revenue;

            $productCost = 0.0;
            if ($isDropshipper) {
                foreach ($order->items as $item) {
                    $sku = $item->lineitem_sku;
                    if ($sku && isset($productCostBySku[$sku])) {
                        $productCost += $productCostBySku[$sku] * $item->lineitem_quantity;
                    }
                }
            }

            $orderCharges = $deliveryFee + $callConfirmFee + $codFee + $otherFee
                          + ($revenue * $vatPercent / 100);

            $orderProfit = $revenue - $productCost - $orderCharges;
            $totalProfit += $orderProfit;

            $month = $order->created_at->format('Y-m');
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = ['orders_count' => 0, 'sold_value' => 0.0, 'profit' => 0.0];
            }
            $monthlyData[$month]['orders_count']++;
            $monthlyData[$month]['sold_value'] += $revenue;
            $monthlyData[$month]['profit'] += $orderProfit;
        }

        krsort($monthlyData);
        $monthlyBreakdown = array_map(function ($data, $month) {
            return [
                'month'        => $month,
                'orders_count' => $data['orders_count'],
                'sold_value'   => round($data['sold_value'], 2),
                'profit'       => round($data['profit'], 2),
            ];
        }, $monthlyData, array_keys($monthlyData));

        $totalReceived = round((float) $client->payments()->sum('amount'), 2);
        $balanceOwed   = round($totalProfit - $totalReceived, 2);

        $perPage  = $request->get('per_page', 15);
        $payments = $client->payments()
            ->with('createdBy:id,name')
            ->orderBy('paid_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'summary' => [
                'total_sold_count' => count($orders),
                'total_sold_value' => round($totalSoldValue, 2),
                'total_profit'     => round($totalProfit, 2),
                'total_received'   => $totalReceived,
                'balance_owed'     => $balanceOwed,
            ],
            'client_type' => $client->client_types ?? [],
            'charges'     => $charges,
            'monthly_breakdown' => array_values($monthlyBreakdown),
            'payments'    => $payments,
        ]);
    }

    public function updateCompanyProfile(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client) {
            abort(403);
        }

        $validated = $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'commercial_registration' => 'nullable|string|max:50',
        ]);

        $client->update($validated);

        return back()->with('success', 'Company profile updated successfully.');
    }

    public function updateLogo(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client) {
            abort(403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        if ($client->logo) {
            Storage::disk('public')->delete($client->logo);
        }

        $path = $request->file('logo')->store('client-logos', 'public');
        $client->update(['logo' => $path]);

        return back()->with('success', 'Company logo updated successfully.');
    }

    public function removeLogo()
    {
        $client = $this->resolveClient();

        if (!$client) {
            abort(403);
        }

        if ($client->logo) {
            Storage::disk('public')->delete($client->logo);
            $client->update(['logo' => null]);
        }

        return back()->with('success', 'Company logo removed.');
    }

    // --- Fulfilment Inventory (client-facing write endpoints) ---

    private function resolveFulfilmentClient(): Client
    {
        $client = $this->resolveClient();

        if (!$client || !$client->is_fulfilment || !in_array('inventory', $client->portal_features ?? [])) {
            abort(403);
        }

        return $client;
    }

    public function storeInventory(Request $request)
    {
        $client = $this->resolveFulfilmentClient();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'quantity'    => 'required|integer|min:0',
            'unit_price'  => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string',
            'images'      => 'nullable|array|max:10',
            'images.*'    => 'image|max:5120',
        ]);

        $productCode = ClientProduct::generateProductCode($client);

        $product = $client->clientProducts()->create([
            'product_code' => $productCode,
            'name'         => $validated['name'],
            'sku'          => $validated['sku'] ?? null,
            'description'  => $validated['description'] ?? null,
            'quantity'     => $validated['quantity'],
            'unit_price'   => $validated['unit_price'] ?? null,
            'notes'        => $validated['notes'] ?? null,
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $position => $file) {
                $path = $file->store("client-products/{$product->id}", 'public');
                $product->images()->create(['path' => $path, 'position' => $position + 1]);
            }
        }

        try {
            User::role(['admin', 'superadmin'])->each(
                fn ($adminUser) => $adminUser->notify(new ProductSubmittedNotification($product, $client))
            );
        } catch (\Exception $e) {
            // Notification failure should not block product creation
        }

        return response()->json([
            'message' => 'Product added successfully',
            'product' => $product->load('images'),
        ], 201);
    }

    public function updateInventory(Request $request, ClientProduct $product)
    {
        $client = $this->resolveFulfilmentClient();

        if ($product->client_id !== $client->id) {
            abort(403);
        }

        if ($product->verification_status === 'verified') {
            abort(422, 'Verified products cannot be edited.');
        }

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'sku'              => 'nullable|string|max:100',
            'description'      => 'nullable|string',
            'quantity'         => 'sometimes|integer|min:0',
            'unit_price'       => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string',
            'images'           => 'nullable|array|max:10',
            'images.*'         => 'image|max:5120',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer',
        ]);

        $product->update($validated);

        if (!empty($validated['remove_image_ids'])) {
            $toDelete = $product->images()->whereIn('id', $validated['remove_image_ids'])->get();
            foreach ($toDelete as $img) {
                Storage::disk('public')->delete($img->path);
                $img->delete();
            }
        }

        if ($request->hasFile('images')) {
            $nextPosition = ($product->images()->max('position') ?? 0) + 1;
            foreach ($request->file('images') as $file) {
                $path = $file->store("client-products/{$product->id}", 'public');
                $product->images()->create(['path' => $path, 'position' => $nextPosition++]);
            }
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh()->load('images'),
        ]);
    }

    public function destroyInventoryImage(ClientProduct $product, ClientProductImage $image)
    {
        $client = $this->resolveFulfilmentClient();

        if ($product->client_id !== $client->id || $image->client_product_id !== $product->id) {
            abort(403);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Image deleted']);
    }

    public function destroyInventory(ClientProduct $product)
    {
        $client = $this->resolveFulfilmentClient();

        if ($product->client_id !== $client->id) {
            abort(403);
        }

        if ($product->verification_status === 'verified') {
            abort(422, 'Verified products cannot be deleted.');
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
