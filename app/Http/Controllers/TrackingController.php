<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipment;
use Inertia\Inertia;

class TrackingController extends Controller
{
    public function search()
    {
        return Inertia::render('TrackingSearch');
    }

    /**
     * JSON endpoint consumed by the TrackingSearch page via AJAX.
     * Accepts: numeric order DB id, order_number, or shipment tracking number.
     */
    public function api(string $identifier)
    {
        $order = $this->resolveOrder($identifier);

        if (! $order || ! $order->latestShipment) {
            return response()->json(['error' => 'Shipment not found.'], 404);
        }

        return response()->json($this->shipmentPayload($order));
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function resolveOrder(string $identifier): ?Order
    {
        // 1. Numeric DB id
        if (is_numeric($identifier)) {
            $order = Order::with('latestShipment')->find((int) $identifier);
            if ($order) return $order;
        }

        // 2. Exact order_number (e.g. "#12962")
        $order = Order::with('latestShipment')
            ->where('order_number', $identifier)
            ->first();
        if ($order) return $order;

        // 3. order_number without leading "#" (user typed "12962", DB has "#12962")
        if (! str_starts_with($identifier, '#')) {
            $order = Order::with('latestShipment')
                ->where('order_number', '#' . $identifier)
                ->first();
            if ($order) return $order;
        }

        // 4. Shipment tracking number (e.g. "UTE019047864077")
        $shipment = Shipment::where('tracking_number', $identifier)->first();
        if ($shipment) {
            return Order::with('latestShipment')->find($shipment->order_id);
        }

        return null;
    }

    private function shipmentPayload(Order $order): array
    {
        $shipment = $order->latestShipment;

        return [
            'order_id'         => $order->id,
            'order_number'     => $order->order_number,
            'customer_name'    => $order->customer_name,
            'tracking_number'  => $shipment->tracking_number,
            'courier'          => $shipment->courier,
            'status'           => $shipment->status,
            'status_label'     => $shipment->status_label,
            'status_color'     => $shipment->status_color,
            'tracking_history' => $shipment->tracking_history ?? [],
            'shipped_at'       => $shipment->shipped_at?->toIso8601String(),
            'delivered_at'     => $shipment->delivered_at?->toIso8601String(),
        ];
    }
}
