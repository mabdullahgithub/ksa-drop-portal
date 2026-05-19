<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of orders with filters.
     */
    public function index(Request $request)
    {
        $query = Order::with('items');

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by fulfillment status
        if ($request->has('fulfillment_status') && $request->fulfillment_status !== 'all') {
            $query->where('fulfillment_status', $request->fulfillment_status);
        }

        // Filter by financial status
        if ($request->has('financial_status') && $request->financial_status !== 'all') {
            $query->where('financial_status', $request->financial_status);
        }

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Filter by UTM source
        if ($request->has('utm_source')) {
            $query->where('utm_source', $request->utm_source);
        }

        // Filter by UTM campaign
        if ($request->has('utm_campaign')) {
            $query->where('utm_campaign', $request->utm_campaign);
        }

        // Filter by risk level
        if ($request->has('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        // Filter by country
        if ($request->has('country')) {
            $query->where('shipping_country', $request->country);
        }

        // Filter by total amount range
        if ($request->has('min_total')) {
            $query->where('total', '>=', $request->min_total);
        }
        if ($request->has('max_total')) {
            $query->where('total', '<=', $request->max_total);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order)
    {
        $order->load('items');
        return response()->json($order);
    }

    /**
     * Update the specified order.
     */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'fulfillment_status' => 'sometimes|in:pending,unfulfilled,fulfilled,cancelled',
            'financial_status' => 'sometimes|in:pending,paid,refunded,partially_refunded',
            'notes' => 'sometimes|string|nullable',
            'tags' => 'sometimes|array',
        ]);

        // Update timestamps based on status changes
        if (isset($validated['fulfillment_status'])) {
            if ($validated['fulfillment_status'] === 'fulfilled' && !$order->fulfilled_at) {
                $validated['fulfilled_at'] = now();
            }
            if ($validated['fulfillment_status'] === 'cancelled' && !$order->cancelled_at) {
                $validated['cancelled_at'] = now();
            }
        }

        if (isset($validated['financial_status'])) {
            if ($validated['financial_status'] === 'paid' && !$order->paid_at) {
                $validated['paid_at'] = now();
                $validated['outstanding_balance'] = 0;
            }
        }

        $order->update($validated);
        $order->load('items');

        return response()->json($order);
    }

    /**
     * Update order fulfillment status.
     */
    public function updateFulfillmentStatus(Request $request, Order $order)
    {
        $request->validate([
            'fulfillment_status' => 'required|in:pending,unfulfilled,fulfilled,cancelled',
        ]);

        $updates = ['fulfillment_status' => $request->fulfillment_status];

        if ($request->fulfillment_status === 'fulfilled' && !$order->fulfilled_at) {
            $updates['fulfilled_at'] = now();
        }

        if ($request->fulfillment_status === 'cancelled' && !$order->cancelled_at) {
            $updates['cancelled_at'] = now();
        }

        $order->update($updates);

        return response()->json([
            'message' => 'Order fulfillment status updated successfully',
            'order' => $order->fresh(['items']),
        ]);
    }

    /**
     * Update order financial status.
     */
    public function updateFinancialStatus(Request $request, Order $order)
    {
        $request->validate([
            'financial_status' => 'required|in:pending,paid,refunded,partially_refunded',
            'refunded_amount' => 'sometimes|numeric|min:0',
        ]);

        $updates = ['financial_status' => $request->financial_status];

        if ($request->financial_status === 'paid' && !$order->paid_at) {
            $updates['paid_at'] = now();
            $updates['outstanding_balance'] = 0;
        }

        if ($request->has('refunded_amount')) {
            $updates['refunded_amount'] = $request->refunded_amount;

            if ($request->refunded_amount >= $order->total) {
                $updates['financial_status'] = 'refunded';
            } elseif ($request->refunded_amount > 0) {
                $updates['financial_status'] = 'partially_refunded';
            }
        }

        $order->update($updates);

        return response()->json([
            'message' => 'Order financial status updated successfully',
            'order' => $order->fresh(['items']),
        ]);
    }

    /**
     * Get order statistics.
     */
    public function statistics(Request $request)
    {
        $query = Order::query();

        // Apply date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $stats = [
            'total_orders' => $query->count(),
            'total_revenue' => round((float) $query->sum('total'), 2),
            'average_order_value' => round((float) $query->avg('total'), 2),
            'by_fulfillment_status' => DB::table('orders')
                ->select('fulfillment_status', DB::raw('count(*) as count'))
                ->groupBy('fulfillment_status')
                ->get(),
            'by_financial_status' => DB::table('orders')
                ->select('financial_status', DB::raw('count(*) as count'))
                ->groupBy('financial_status')
                ->get(),
            'by_payment_method' => DB::table('orders')
                ->select('payment_method', DB::raw('count(*) as count'))
                ->groupBy('payment_method')
                ->get(),
            'by_utm_source' => DB::table('orders')
                ->select('utm_source', DB::raw('count(*) as count'))
                ->whereNotNull('utm_source')
                ->groupBy('utm_source')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_country' => DB::table('orders')
                ->select('shipping_country', DB::raw('count(*) as count'))
                ->whereNotNull('shipping_country')
                ->groupBy('shipping_country')
                ->orderByDesc('count')
                ->get(),
            'top_products' => DB::table('order_items')
                ->select('lineitem_name', DB::raw('sum(lineitem_quantity) as total_quantity'), DB::raw('sum(lineitem_price * lineitem_quantity) as total_revenue'))
                ->groupBy('lineitem_name')
                ->orderByDesc('total_quantity')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Get filter options for dropdowns.
     */
    public function filterOptions()
    {
        return response()->json([
            'fulfillment_statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'unfulfilled', 'label' => 'Unfulfilled'],
                ['value' => 'fulfilled', 'label' => 'Fulfilled'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'financial_statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'refunded', 'label' => 'Refunded'],
                ['value' => 'partially_refunded', 'label' => 'Partially Refunded'],
            ],
            'payment_methods' => DB::table('orders')
                ->select('payment_method')
                ->distinct()
                ->whereNotNull('payment_method')
                ->pluck('payment_method')
                ->map(fn($method) => ['value' => $method, 'label' => $method])
                ->prepend(['value' => 'all', 'label' => 'All'])
                ->values(),
            'utm_sources' => DB::table('orders')
                ->select('utm_source')
                ->distinct()
                ->whereNotNull('utm_source')
                ->pluck('utm_source')
                ->map(fn($source) => ['value' => $source, 'label' => ucfirst($source)])
                ->values(),
            'countries' => DB::table('orders')
                ->select('shipping_country')
                ->distinct()
                ->whereNotNull('shipping_country')
                ->pluck('shipping_country')
                ->map(fn($country) => ['value' => $country, 'label' => $country])
                ->values(),
            'risk_levels' => [
                ['value' => 'Low', 'label' => 'Low'],
                ['value' => 'Medium', 'label' => 'Medium'],
                ['value' => 'High', 'label' => 'High'],
            ],
        ]);
    }

    /**
     * Bulk update orders.
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'action' => 'required|in:update_fulfillment,update_financial,add_tags,cancel',
            'fulfillment_status' => 'required_if:action,update_fulfillment',
            'financial_status' => 'required_if:action,update_financial',
            'tags' => 'required_if:action,add_tags|array',
        ]);

        $orders = Order::whereIn('id', $request->order_ids)->get();

        foreach ($orders as $order) {
            switch ($request->action) {
                case 'update_fulfillment':
                    $updates = ['fulfillment_status' => $request->fulfillment_status];
                    if ($request->fulfillment_status === 'fulfilled' && !$order->fulfilled_at) {
                        $updates['fulfilled_at'] = now();
                    }
                    $order->update($updates);
                    break;

                case 'update_financial':
                    $updates = ['financial_status' => $request->financial_status];
                    if ($request->financial_status === 'paid' && !$order->paid_at) {
                        $updates['paid_at'] = now();
                        $updates['outstanding_balance'] = 0;
                    }
                    $order->update($updates);
                    break;

                case 'add_tags':
                    $existingTags = $order->tags ?? [];
                    $newTags = array_unique(array_merge($existingTags, $request->tags));
                    $order->update(['tags' => $newTags]);
                    break;

                case 'cancel':
                    $order->update([
                        'fulfillment_status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);
                    break;
            }
        }

        return response()->json([
            'message' => 'Orders updated successfully',
            'updated_count' => $orders->count(),
        ]);
    }

    /**
     * Export orders to CSV.
     */
    public function export(Request $request)
    {
        $query = Order::with('items');

        // Apply same filters as index
        if ($request->has('search')) {
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

            // Headers - matching the Shopify format
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
                'Id', 'Tags', 'Risk Level', 'Source'
            ]);

            // Data
            foreach ($orders as $order) {
                // Get first item or create empty row
                $firstItem = $order->items->first();

                fputcsv($file, [
                    $order->order_number,
                    $order->customer_email,
                    $order->financial_status,
                    $order->paid_at,
                    $order->fulfillment_status,
                    $order->fulfilled_at,
                    $order->accepts_marketing ? 'yes' : 'no',
                    $order->currency,
                    $order->subtotal,
                    $order->shipping,
                    $order->taxes,
                    $order->total,
                    $order->discount_code,
                    $order->discount_amount,
                    $order->shipping_method,
                    $order->created_at,
                    $firstItem?->lineitem_quantity ?? '',
                    $firstItem?->lineitem_name ?? '',
                    $firstItem?->lineitem_price ?? '',
                    $firstItem?->lineitem_compare_at_price ?? '',
                    $firstItem?->lineitem_sku ?? '',
                    $firstItem?->lineitem_requires_shipping ? 'true' : 'false',
                    $firstItem?->lineitem_taxable ? 'true' : 'false',
                    $firstItem?->lineitem_fulfillment_status ?? '',
                    $order->billing_name,
                    $order->billing_street,
                    $order->billing_address1,
                    $order->billing_address2,
                    $order->billing_company,
                    $order->billing_city,
                    $order->billing_zip,
                    $order->billing_province,
                    $order->billing_country,
                    $order->billing_phone,
                    $order->shipping_name,
                    $order->shipping_street,
                    $order->shipping_address1,
                    $order->shipping_address2,
                    $order->shipping_company,
                    $order->shipping_city,
                    $order->shipping_zip,
                    $order->shipping_province,
                    $order->shipping_country,
                    $order->shipping_phone,
                    $order->notes,
                    is_array($order->note_attributes) ? json_encode($order->note_attributes) : ($order->note_attributes ?? ''),
                    $order->cancelled_at,
                    $order->payment_method,
                    $order->payment_reference,
                    $order->refunded_amount,
                    $order->vendor,
                    $order->outstanding_balance,
                    $order->employee,
                    $order->location,
                    $order->device_id,
                    $order->id,
                    is_array($order->tags) ? implode(', ', $order->tags) : ($order->tags ?? ''),
                    $order->risk_level,
                    $order->source,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import orders from CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        // Open CSV file properly to handle multi-line fields
        $handle = fopen($path, 'r');
        if (!$handle) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to open CSV file',
            ], 422);
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'Invalid CSV format - no headers found',
            ], 422);
        }

        $imported = 0;
        $errors = [];
        $duplicates = 0;
        $invalidRows = 0;
        $totalRows = 0;

        DB::beginTransaction();

        try {
            // Read data rows
            while (($row = fgetcsv($handle)) !== false) {
                $totalRows++;

                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        $invalidRows++;
                        continue;
                    }

                    // Ensure row has same number of columns as headers
                    if (count($row) !== count($headers)) {
                        $invalidRows++;
                        $errors[] = [
                            'row' => $totalRows,
                            'reason' => 'Column count mismatch',
                            'details' => 'Expected ' . count($headers) . ' columns, got ' . count($row)
                        ];
                        continue;
                    }

                    // Map CSV columns to database fields
                    $data = array_combine($headers, $row);

                    // Extract order number
                    $orderNumber = $data['Name'] ?? null;

                    if (!$orderNumber) {
                        $invalidRows++;
                        $errors[] = [
                            'row' => $totalRows,
                            'reason' => 'Missing order number',
                            'details' => 'Order number (Name) field is required'
                        ];
                        continue;
                    }

                    // Check if order already exists
                    if (Order::where('order_number', $orderNumber)->exists()) {
                        $duplicates++;
                        continue;
                    }

                    // Parse dates safely
                    $createdAt = now();
                    if (!empty($data['Created at'])) {
                        try {
                            $createdAt = date('Y-m-d H:i:s', strtotime($data['Created at']));
                        } catch (\Exception $e) {
                            // Use current time if date parsing fails
                        }
                    }

                    $paidAt = null;
                    if (!empty($data['Paid at'])) {
                        try {
                            $paidAt = date('Y-m-d H:i:s', strtotime($data['Paid at']));
                        } catch (\Exception $e) {
                            // Leave as null if parsing fails
                        }
                    }

                    $fulfilledAt = null;
                    if (!empty($data['Fulfilled at'])) {
                        try {
                            $fulfilledAt = date('Y-m-d H:i:s', strtotime($data['Fulfilled at']));
                        } catch (\Exception $e) {
                            // Leave as null if parsing fails
                        }
                    }

                    $cancelledAt = null;
                    if (!empty($data['Cancelled at'])) {
                        try {
                            $cancelledAt = date('Y-m-d H:i:s', strtotime($data['Cancelled at']));
                        } catch (\Exception $e) {
                            // Leave as null if parsing fails
                        }
                    }

                    // Create order
                    $order = Order::create([
                    'order_number' => $orderNumber,
                    'customer_name' => $data['Billing Name'] ?? $data['Shipping Name'] ?? null,
                    'customer_email' => $data['Email'] ?? null,
                    'customer_phone' => $data['Billing Phone'] ?? $data['Shipping Phone'] ?? null,
                    'financial_status' => strtolower($data['Financial Status'] ?? 'pending'),
                    'fulfillment_status' => strtolower($data['Fulfillment Status'] ?? 'unfulfilled'),
                    'accepts_marketing' => strtolower($data['Accepts Marketing'] ?? 'no') === 'yes',
                    'currency' => $data['Currency'] ?? 'SAR',
                    'subtotal' => floatval($data['Subtotal'] ?? 0),
                    'shipping' => floatval($data['Shipping'] ?? 0),
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

                    // Create order item if lineitem data exists
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
                    // Skip this row if there's an error
                    $invalidRows++;
                    $errors[] = [
                        'row' => $totalRows,
                        'order_number' => $orderNumber ?? 'Unknown',
                        'reason' => 'Processing error',
                        'details' => $rowError->getMessage()
                    ];
                    // Continue processing other rows
                    continue;
                }
            }

            fclose($handle);
            DB::commit();

            // Build success message
            $message = "Import completed successfully";
            $summary = [];

            if ($imported > 0) {
                $summary[] = "{$imported} order(s) imported";
            }
            if ($duplicates > 0) {
                $summary[] = "{$duplicates} duplicate(s) skipped";
            }
            if ($invalidRows > 0) {
                $summary[] = "{$invalidRows} invalid row(s) skipped";
            }

            if (!empty($summary)) {
                $message .= ": " . implode(', ', $summary);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'duplicates' => $duplicates,
                'invalid' => $invalidRows,
                'total' => $totalRows,
                'errors' => $errors, // Detailed error information
                'has_errors' => !empty($errors),
            ]);

        } catch (\Exception $e) {
            fclose($handle);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'errors' => $errors,
            ], 422);
        }
    }
}
