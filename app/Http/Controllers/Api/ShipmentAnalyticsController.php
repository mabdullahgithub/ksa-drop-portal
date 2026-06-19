<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 14, 30, 90]) ? $days : 30;
        $since = now()->subDays($days);

        $base = Shipment::where('created_at', '>=', $since);

        $totals = (clone $base)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
            SUM(CASE WHEN status = 'exception' THEN 1 ELSE 0 END) as exceptions,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status IN ('info_received','in_transit','out_for_delivery','attempt_fail') THEN 1 ELSE 0 END) as in_transit,
            AVG(CASE WHEN status = 'delivered' AND delivered_at IS NOT NULL AND shipped_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, shipped_at, delivered_at) / 24.0
                ELSE NULL END) as avg_delivery_days
        ")->first();

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        $daily = (clone $base)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as created, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $exceptions = Shipment::where('status', ShipmentStatus::EXCEPTION->value)
            ->with('order:id,order_number,customer_name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'order_id', 'tracking_number', 'courier_status_description', 'exception_note', 'exception_escalated_at', 'created_at']);

        $returns = Shipment::where('status', ShipmentStatus::RETURNED->value)
            ->with('order:id,order_number,customer_name')
            ->orderByDesc('cancelled_at')
            ->limit(20)
            ->get(['id', 'order_id', 'tracking_number', 'cancel_reason', 'cancelled_at']);

        $cod = Shipment::whereHas('order', fn ($q) => $q->whereNotNull('cod_collected_amount'))
            ->join('orders', 'shipments.order_id', '=', 'orders.id')
            ->select(
                'shipments.id',
                'shipments.tracking_number',
                'orders.order_number',
                'orders.customer_name',
                'orders.total',
                'orders.cod_collected_amount',
                'orders.cod_collected_at',
                'orders.financial_status',
            )
            ->orderByDesc('orders.cod_collected_at')
            ->limit(50)
            ->get();

        $codSummary = Shipment::join('orders', 'shipments.order_id', '=', 'orders.id')
            ->whereNotNull('orders.cod_collected_amount')
            ->selectRaw('COUNT(*) as total_collected, SUM(orders.cod_collected_amount) as total_amount')
            ->first();

        return response()->json([
            'period_days'  => $days,
            'totals'       => $totals,
            'by_status'    => $byStatus,
            'daily'        => $daily,
            'exceptions'   => $exceptions,
            'returns'      => $returns,
            'cod'          => [
                'summary'      => $codSummary,
                'transactions' => $cod,
            ],
        ]);
    }
}
