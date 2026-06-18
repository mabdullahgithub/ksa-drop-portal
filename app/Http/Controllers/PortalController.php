<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\ClientProductImage;
use App\Models\User;
use App\Notifications\ProductSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    private function resolveClient(): ?Client
    {
        if (session()->has('impersonate.client_id')) {
            return Client::find(session('impersonate.client_id'));
        }

        return auth()->user()->client ?? null;
    }

    public function dashboard()
    {
        $client = $this->resolveClient();

        if (!$client) {
            abort(403);
        }

        $stats = [
            'total_orders' => $client->orders()->count(),
            'total_revenue' => round((float) $client->orders()->where('financial_status', 'paid')->sum('total'), 2),
            'pending_orders' => $client->orders()->where('fulfillment_status', 'pending')->count(),
            'total_products' => $client->clientProducts()->count(),
            'verified_products' => $client->clientProducts()->verified()->count(),
            'pending_verification' => $client->clientProducts()->pending()->count(),
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

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('fulfillment_status') && $request->fulfillment_status !== 'all') {
            $query->where('fulfillment_status', $request->fulfillment_status);
        }

        if ($request->has('financial_status') && $request->financial_status !== 'all') {
            $query->where('financial_status', $request->financial_status);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function importOrders(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:51200',
        ]);

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

        // Always prefix imported order numbers with a client identifier so they
        // stay unique across clients (order_number has a global unique index).
        // Prefer the human-friendly short_id; fall back to the client id so a
        // missing short_id can never produce an unprefixed, collision-prone number.
        // Format: "{prefix}{number}" e.g. "EVENIE31902".
        $prefix = $client->short_id ?: 'CL' . $client->id;

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
                    $rawOrderNumber = $data['Name'] ?? null;

                    if (!$rawOrderNumber) {
                        $invalidRows++;
                        $errors[] = ['row' => $totalRows, 'reason' => 'Missing order number', 'details' => 'Order number (Name) field is required'];
                        continue;
                    }

                    // Customer name and phone are required so every order is deliverable.
                    // All other fields are optional.
                    $customerName = trim((string) ($data['Billing Name'] ?? $data['Shipping Name'] ?? ''));
                    $customerPhone = trim((string) ($data['Phone'] ?? $data['Billing Phone'] ?? $data['Shipping Phone'] ?? ''));

                    if ($customerName === '') {
                        $invalidRows++;
                        $errors[] = ['row' => $totalRows, 'order_number' => $rawOrderNumber, 'reason' => 'Missing customer name', 'details' => 'Customer name (Billing Name or Shipping Name) is required'];
                        continue;
                    }

                    if ($customerPhone === '') {
                        $invalidRows++;
                        $errors[] = ['row' => $totalRows, 'order_number' => $rawOrderNumber, 'reason' => 'Missing phone', 'details' => 'Phone (Phone, Billing Phone or Shipping Phone) is required'];
                        continue;
                    }

                    // Prefix with the client identifier so orders from different
                    // clients are distinguishable and unique. The raw Shopify name
                    // (e.g. "#31902") is stripped to its digits and joined directly
                    // to the prefix, e.g. client EVENIE → "EVENIE31902".
                    $rawDigits = preg_replace('/\D+/', '', (string) $rawOrderNumber);
                    // Fall back to the trimmed raw value when there are no digits,
                    // so non-numeric names don't collapse into a bare prefix.
                    $orderSuffix = $rawDigits !== '' ? $rawDigits : ltrim(trim((string) $rawOrderNumber), '#');
                    $orderNumber = $prefix . $orderSuffix;

                    if (\App\Models\Order::where('order_number', $orderNumber)->exists()) {
                        $duplicates++;
                        continue;
                    }

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

                    $order = \App\Models\Order::create([
                        'client_id' => $client->id,
                        'order_number' => $orderNumber,
                        'customer_name' => $customerName,
                        'customer_email' => $data['Email'] ?? null,
                        'customer_phone' => $customerPhone,
                        'financial_status' => strtolower($data['Financial Status'] ?? 'pending'),
                        'fulfillment_status' => strtolower($data['Fulfillment Status'] ?? 'unfulfilled'),
                        'accepts_marketing' => strtolower($data['Accepts Marketing'] ?? 'no') === 'yes',
                        'currency' => $data['Currency'] ?? 'SAR',
                        'subtotal' => floatval($data['Subtotal'] ?? 0),
                        'shipping_cost' => floatval($data['Shipping'] ?? 0),
                        'taxes' => floatval($data['Taxes'] ?? 0),
                        'total' => floatval($data['Total'] ?? 0),
                        'discount_code' => $data['Discount Code'] ?? null,
                        'discount_amount' => floatval($data['Discount Amount'] ?? 0),
                        'shipping_method' => $data['Shipping Method'] ?? null,
                        'billing_name' => $data['Billing Name'] ?? null,
                        'billing_street' => $data['Billing Street'] ?? null,
                        'billing_address1' => $data['Billing Address1'] ?? null,
                        'billing_address2' => $data['Billing Address2'] ?? null,
                        'billing_company' => $data['Billing Company'] ?? null,
                        'billing_city' => $data['Billing City'] ?? null,
                        'billing_zip' => $data['Billing Zip'] ?? null,
                        'billing_province' => $data['Billing Province'] ?? null,
                        'billing_country' => $data['Billing Country'] ?? null,
                        'billing_phone' => $data['Billing Phone'] ?? null,
                        'shipping_name' => $data['Shipping Name'] ?? null,
                        'shipping_street' => $data['Shipping Street'] ?? null,
                        'shipping_address1' => $data['Shipping Address1'] ?? null,
                        'shipping_address2' => $data['Shipping Address2'] ?? null,
                        'shipping_company' => $data['Shipping Company'] ?? null,
                        'shipping_city' => $data['Shipping City'] ?? null,
                        'shipping_zip' => $data['Shipping Zip'] ?? null,
                        'shipping_province' => $data['Shipping Province'] ?? null,
                        'shipping_country' => $data['Shipping Country'] ?? null,
                        'shipping_phone' => $data['Shipping Phone'] ?? null,
                        'notes' => $data['Notes'] ?? null,
                        'note_attributes' => $data['Note Attributes'] ?? null,
                        'payment_method' => $data['Payment Method'] ?? null,
                        'payment_reference' => $data['Payment Reference'] ?? null,
                        'refunded_amount' => floatval($data['Refunded Amount'] ?? 0),
                        'vendor' => $data['Vendor'] ?? null,
                        'outstanding_balance' => floatval($data['Outstanding Balance'] ?? 0),
                        'employee' => $data['Employee'] ?? null,
                        'location' => $data['Location'] ?? null,
                        'device_id' => $data['Device ID'] ?? null,
                        'tags' => !empty($data['Tags']) ? explode(', ', $data['Tags']) : [],
                        'risk_level' => $data['Risk Level'] ?? 'Low',
                        'source' => $data['Source'] ?? null,
                        'paid_at' => $paidAt,
                        'fulfilled_at' => $fulfilledAt,
                        'cancelled_at' => $cancelledAt,
                        'created_at' => $createdAt,
                    ]);

                    if (!empty($data['Lineitem name'])) {
                        $order->items()->create([
                            'lineitem_quantity' => intval($data['Lineitem quantity'] ?? 1),
                            'lineitem_name' => $data['Lineitem name'],
                            'lineitem_price' => floatval($data['Lineitem price'] ?? 0),
                            'lineitem_compare_at_price' => floatval($data['Lineitem compare at price'] ?? 0),
                            'lineitem_sku' => $data['Lineitem sku'] ?? null,
                            'lineitem_requires_shipping' => strtolower($data['Lineitem requires shipping'] ?? 'false') === 'true',
                            'lineitem_taxable' => strtolower($data['Lineitem taxable'] ?? 'false') === 'true',
                            'lineitem_fulfillment_status' => $data['Lineitem fulfillment status'] ?? 'unfulfilled',
                        ]);
                    }

                    $imported++;
                } catch (\Exception $rowError) {
                    $invalidRows++;
                    $errors[] = ['row' => $totalRows, 'order_number' => $orderNumber ?? 'Unknown', 'reason' => 'Processing error', 'details' => $rowError->getMessage()];
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

    public function ordersImportTemplate(Request $request)
    {
        $client = $this->resolveClient();

        if (!$client || !in_array('orders', $client->portal_features ?? [])) {
            abort(403);
        }

        $columns = [
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
        ];

        // A sample row so clients can see the expected format/values for each column.
        $sample = [
            '#1001', 'customer@example.com', 'pending', '', 'unfulfilled', '',
            'no', 'SAR', '100.00', '15.00', '0.00', '115.00',
            '', '0.00', 'Standard', date('Y-m-d H:i:s'),
            '1', 'Sample Product', '100.00', '0.00',
            'SKU-001', 'true', 'false', 'unfulfilled',
            'John Doe', '123 King Fahd Rd', '123 King Fahd Rd', '', '',
            'Riyadh', '12345', 'Riyadh', 'SA', '+966500000000',
            'John Doe', '123 King Fahd Rd', '123 King Fahd Rd', '', '',
            'Riyadh', '12345', 'Riyadh', 'SA', '+966500000000',
            '', '', '', 'Cash on Delivery', '',
            '0.00', '', '0.00', '', '', '',
            '', '', 'Low', 'manual',
        ];

        $filename = 'orders_import_template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($columns, $sample) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, $sample);
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

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }
        if ($request->has('fulfillment_status') && $request->fulfillment_status !== 'all') {
            $query->where('fulfillment_status', $request->fulfillment_status);
        }
        if ($request->has('financial_status') && $request->financial_status !== 'all') {
            $query->where('financial_status', $request->financial_status);
        }

        $orders = $query->get();
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
                    $order->currency, $order->subtotal, $order->shipping, $order->taxes, $order->total,
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

        if (!$client || !$client->is_dropshipper || !in_array('products', $client->portal_features ?? [])) {
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

        if (!$client || !$client->is_dropshipper || !in_array('products', $client->portal_features ?? [])) {
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

        if (!$client || !$client->is_dropshipper || !in_array('products', $client->portal_features ?? [])) {
            abort(403);
        }

        $base = \App\Models\Product::where('published', true);

        $vendors = (clone $base)
            ->select('vendor')
            ->distinct()
            ->whereNotNull('vendor')
            ->orderBy('vendor')
            ->pluck('vendor')
            ->map(fn($v) => ['value' => $v, 'label' => $v]);

        $types = (clone $base)
            ->select('type')
            ->distinct()
            ->whereNotNull('type')
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

        $ordersQuery = $client->orders()->where('created_at', '>=', $startDate);

        $totalPaid = round((float) $ordersQuery->clone()->where('financial_status', 'paid')->sum('total'), 2);
        $totalPending = round((float) $ordersQuery->clone()->where('financial_status', 'pending')->sum('total'), 2);
        $totalRefunded = round((float) $ordersQuery->clone()->whereIn('financial_status', ['refunded', 'partially_refunded'])->sum('refunded_amount'), 2);
        $totalOutstanding = round((float) $ordersQuery->clone()->where('outstanding_balance', '>', 0)->sum('outstanding_balance'), 2);

        $statusBreakdown = $client->orders()
            ->where('created_at', '>=', $startDate)
            ->selectRaw("financial_status, COUNT(*) as count, SUM(total) as total_amount")
            ->groupBy('financial_status')
            ->get();

        $monthlyBreakdown = $client->orders()
            ->where('created_at', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, financial_status, COUNT(*) as orders_count, SUM(total) as total_amount, SUM(refunded_amount) as refunded")
            ->groupBy('month', 'financial_status')
            ->orderBy('month', 'desc')
            ->get()
            ->groupBy('month')
            ->map(function ($items, $month) {
                $paid = $items->firstWhere('financial_status', 'paid');
                $pending = $items->firstWhere('financial_status', 'pending');
                $refunded = $items->filter(fn ($i) => in_array($i->financial_status, ['refunded', 'partially_refunded']));

                return [
                    'month' => $month,
                    'paid_amount' => round((float) ($paid->total_amount ?? 0), 2),
                    'paid_count' => (int) ($paid->orders_count ?? 0),
                    'pending_amount' => round((float) ($pending->total_amount ?? 0), 2),
                    'pending_count' => (int) ($pending->orders_count ?? 0),
                    'refunded_amount' => round((float) $refunded->sum('refunded'), 2),
                    'refunded_count' => (int) $refunded->sum('orders_count'),
                    'total_orders' => (int) $items->sum('orders_count'),
                ];
            })
            ->values();

        $transactions = $client->orders()
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc');

        if ($request->has('financial_status') && $request->financial_status !== 'all') {
            $transactions->where('financial_status', $request->financial_status);
        }

        if ($request->has('search') && $request->search) {
            $transactions->search($request->search);
        }

        $perPage = $request->get('per_page', 15);

        $paginatedTransactions = $transactions->paginate($perPage, [
            'id', 'order_number', 'customer_name', 'total', 'subtotal',
            'shipping_cost', 'taxes', 'discount_amount', 'refunded_amount',
            'outstanding_balance', 'financial_status', 'payment_method',
            'payment_reference', 'currency', 'paid_at', 'created_at',
        ]);

        return response()->json([
            'summary' => [
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'total_refunded' => $totalRefunded,
                'total_outstanding' => $totalOutstanding,
                'net_revenue' => round($totalPaid - $totalRefunded, 2),
            ],
            'status_breakdown' => $statusBreakdown,
            'monthly_breakdown' => $monthlyBreakdown,
            'transactions' => $paginatedTransactions,
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
