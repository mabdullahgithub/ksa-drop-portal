<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Order;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of orders with filters.
     */
    public function index(Request $request)
    {
        $query = Order::with(['items', 'client', 'latestShipment']);

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by fulfillment status (multi-select)
        if ($request->has('fulfillment_status')) {
            $values = $this->multiValue($request->fulfillment_status);
            if (!empty($values)) {
                $query->whereIn('fulfillment_status', $values);
            }
        }

        // Filter by financial status (multi-select)
        if ($request->has('financial_status')) {
            $values = $this->multiValue($request->financial_status);
            if (!empty($values)) {
                $query->whereIn('financial_status', $values);
            }
        }

        // Filter by payment method (multi-select)
        if ($request->has('payment_method')) {
            $values = $this->multiValue($request->payment_method);
            if (!empty($values)) {
                $query->whereIn('payment_method', $values);
            }
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Filter by UTM source (multi-select)
        if ($request->has('utm_source')) {
            $values = $this->multiValue($request->utm_source);
            if (!empty($values)) {
                $query->whereIn('utm_source', $values);
            }
        }

        // Filter by tags (match orders carrying any of the selected tags)
        if ($request->has('tags')) {
            $tags = $this->multiValue($request->tags);
            if (!empty($tags)) {
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }
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

        // Filter by client IDs (multi-select)
        if ($request->has('client_ids')) {
            $clientIds = is_array($request->client_ids)
                ? $request->client_ids
                : explode(',', $request->client_ids);
            $clientIds = array_filter(array_map('intval', $clientIds));
            if (!empty($clientIds)) {
                $query->whereIn('client_id', $clientIds);
            }
        }

        // Filter by shipment status
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
            $statuses = $this->multiValue($request->shipment_status);
            if (!empty($statuses)) {
                $query->whereHas('shipments', function ($q) use ($statuses) {
                    $q->whereIn('status', $statuses);
                });
            }
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
     * Normalise a multi-select filter value (array or comma-separated string)
     * into a clean list, dropping empties and the legacy "all" sentinel.
     */
    private function multiValue($value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(
            array_map('trim', $values),
            fn ($v) => $v !== '' && $v !== 'all'
        ));
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order)
    {
        $order->load(['items.clientProduct', 'items.product', 'latestShipment', 'invoices']);
        return response()->json($order);
    }

    /**
     * Update the specified order.
     */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            // Customer
            'customer_name' => 'sometimes|nullable|string|max:255',
            'customer_email' => 'sometimes|nullable|email|max:255',
            'customer_phone' => 'sometimes|nullable|string|max:50',

            // Billing address
            'billing_name' => 'sometimes|nullable|string|max:255',
            'billing_street' => 'sometimes|nullable|string|max:255',
            'billing_address1' => 'sometimes|nullable|string|max:255',
            'billing_address2' => 'sometimes|nullable|string|max:255',
            'billing_company' => 'sometimes|nullable|string|max:255',
            'billing_city' => 'sometimes|nullable|string|max:255',
            'billing_zip' => 'sometimes|nullable|string|max:50',
            'billing_province' => 'sometimes|nullable|string|max:255',
            'billing_country' => 'sometimes|nullable|string|max:255',
            'billing_phone' => 'sometimes|nullable|string|max:50',

            // Shipping address
            'shipping_name' => 'sometimes|nullable|string|max:255',
            'shipping_street' => 'sometimes|nullable|string|max:255',
            'shipping_address1' => 'sometimes|nullable|string|max:255',
            'shipping_address2' => 'sometimes|nullable|string|max:255',
            'shipping_company' => 'sometimes|nullable|string|max:255',
            'shipping_city' => 'sometimes|nullable|string|max:255',
            'shipping_zip' => 'sometimes|nullable|string|max:50',
            'shipping_province' => 'sometimes|nullable|string|max:255',
            'shipping_country' => 'sometimes|nullable|string|max:255',
            'shipping_phone' => 'sometimes|nullable|string|max:50',

            // Status
            'fulfillment_status' => 'sometimes|in:pending,unfulfilled,fulfilled,cancelled',
            'financial_status' => 'sometimes|in:pending,paid,refunded,partially_refunded',

            // Payment & amounts
            'payment_method' => 'sometimes|nullable|string|max:255',
            'payment_reference' => 'sometimes|nullable|string|max:255',
            'currency' => 'sometimes|nullable|string|max:10',
            'subtotal' => 'sometimes|numeric|min:0',
            'shipping_cost' => 'sometimes|numeric|min:0',
            'taxes' => 'sometimes|numeric|min:0',
            'total' => 'sometimes|numeric|min:0',
            'discount_code' => 'sometimes|nullable|string|max:255',
            'discount_amount' => 'sometimes|numeric|min:0',
            'shipping_method' => 'sometimes|nullable|string|max:255',
            'outstanding_balance' => 'sometimes|numeric|min:0',
            'refunded_amount' => 'sometimes|numeric|min:0',

            // Meta
            'risk_level' => 'sometimes|nullable|string|max:50',
            'source' => 'sometimes|nullable|string|max:255',
            'vendor' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|string|nullable',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:255',
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
            'unassigned_orders' => $query->clone()->withoutShipment()->count(),
            'assigned_orders' => $query->clone()->withShipment()->count(),
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
            'by_shipment_status' => DB::table('shipments')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->orderByDesc('count')
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
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'unfulfilled', 'label' => 'Unfulfilled'],
                ['value' => 'fulfilled', 'label' => 'Fulfilled'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'financial_statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'refunded', 'label' => 'Refunded'],
                ['value' => 'partially_refunded', 'label' => 'Partially Refunded'],
            ],
            'shipment_statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'info_received', 'label' => 'Info Received'],
                ['value' => 'in_transit', 'label' => 'In Transit'],
                ['value' => 'out_for_delivery', 'label' => 'Out for Delivery'],
                ['value' => 'delivered', 'label' => 'Delivered'],
                ['value' => 'attempt_fail', 'label' => 'Attempt Failed'],
                ['value' => 'returned', 'label' => 'Returned'],
                ['value' => 'exception', 'label' => 'Exception'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
                ['value' => 'failed', 'label' => 'Failed'],
            ],
            'payment_methods' => DB::table('orders')
                ->select('payment_method')
                ->distinct()
                ->whereNotNull('payment_method')
                ->pluck('payment_method')
                ->map(fn($method) => ['value' => $method, 'label' => $method])
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
            'tags' => Tag::orderBy('name')
                ->pluck('name')
                ->map(fn($name) => ['value' => $name, 'label' => $name])
                ->values(),
            'clients' => Client::select('id', 'company_name', 'short_id')
                ->orderBy('company_name')
                ->get()
                ->map(fn($c) => ['value' => $c->id, 'label' => $c->company_name, 'client_id' => $c->short_id])
                ->values(),
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
                    $existingTags = is_array($order->tags) ? $order->tags : [];
                    $newTags = array_values(array_unique(array_merge($existingTags, $request->tags)));
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
        if ($request->has('fulfillment_status')) {
            $values = $this->multiValue($request->fulfillment_status);
            if (!empty($values)) {
                $query->whereIn('fulfillment_status', $values);
            }
        }
        if ($request->has('financial_status')) {
            $values = $this->multiValue($request->financial_status);
            if (!empty($values)) {
                $query->whereIn('financial_status', $values);
            }
        }
        if ($request->has('payment_method')) {
            $values = $this->multiValue($request->payment_method);
            if (!empty($values)) {
                $query->whereIn('payment_method', $values);
            }
        }
        if ($request->has('utm_source')) {
            $values = $this->multiValue($request->utm_source);
            if (!empty($values)) {
                $query->whereIn('utm_source', $values);
            }
        }
        if ($request->has('tags')) {
            $tags = $this->multiValue($request->tags);
            if (!empty($tags)) {
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }
        }
        if ($request->has('client_ids')) {
            $clientIds = is_array($request->client_ids)
                ? $request->client_ids
                : explode(',', $request->client_ids);
            $clientIds = array_filter(array_map('intval', $clientIds));
            if (!empty($clientIds)) {
                $query->whereIn('client_id', $clientIds);
            }
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
                    $order->shipping_cost,
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

}
