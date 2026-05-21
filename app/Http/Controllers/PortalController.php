<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function dashboard()
    {
        $client = auth()->user()->client;

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
        $client = auth()->user()->client;

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

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function inventory(Request $request)
    {
        $client = auth()->user()->client;

        if (!$client || !in_array('inventory', $client->portal_features ?? [])) {
            abort(403);
        }

        $query = $client->clientProducts();

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('verification_status') && $request->verification_status !== 'all') {
            $query->where('verification_status', $request->verification_status);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function products(Request $request)
    {
        $client = auth()->user()->client;

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

    public function revenue(Request $request)
    {
        $client = auth()->user()->client;

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
        $client = auth()->user()->client;

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
        $client = auth()->user()->client;

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
        $client = auth()->user()->client;

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
        $client = auth()->user()->client;

        if (!$client) {
            abort(403);
        }

        if ($client->logo) {
            Storage::disk('public')->delete($client->logo);
            $client->update(['logo' => null]);
        }

        return back()->with('success', 'Company logo removed.');
    }
}
