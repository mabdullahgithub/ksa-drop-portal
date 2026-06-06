<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrackingController extends Controller
{
    public function show(string $trackingNumber)
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            return Inertia::render('Tracking', [
                'shipment' => null,
                'error' => 'Shipment not found.',
            ]);
        }

        return Inertia::render('Tracking', [
            'shipment' => [
                'tracking_number' => $shipment->tracking_number,
                'courier' => $shipment->courier,
                'status' => $shipment->status,
                'status_label' => $shipment->status_label,
                'status_color' => $shipment->status_color,
                'tracking_history' => $shipment->tracking_history ?? [],
                'shipped_at' => $shipment->shipped_at?->toIso8601String(),
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            ],
        ]);
    }

    public function api(string $trackingNumber)
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            return response()->json(['error' => 'Shipment not found.'], 404);
        }

        return response()->json([
            'tracking_number' => $shipment->tracking_number,
            'courier' => $shipment->courier,
            'status' => $shipment->status,
            'status_label' => $shipment->status_label,
            'status_color' => $shipment->status_color,
            'tracking_history' => $shipment->tracking_history ?? [],
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
        ]);
    }
}
