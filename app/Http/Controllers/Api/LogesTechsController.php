<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shipping\Drivers\LogesTechsDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LogesTechs-specific lookups backing the Create Shipment dialog.
 *
 * LogesTechs resolves a destination from a district ("village") rather than the
 * free-text province/city the other couriers accept, and district names are not
 * unique — the live list contains two separate "Riyadh" entries under different
 * cities. The dialog therefore needs a live picker that captures the *id*, not
 * just the typed name, which is what this endpoint feeds.
 */
class LogesTechsController extends Controller
{
    public function villages(Request $request, LogesTechsDriver $driver): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        try {
            $villages = $driver->getVillages($validated['search'] ?? null);
        } catch (\RuntimeException $e) {
            // Credentials not configured yet — a clean 422 lets the dialog show
            // a "configure LogesTechs first" hint instead of an empty dropdown
            // that looks like the courier simply has no districts.
            return response()->json([
                'message' => 'LogesTechs is not configured yet. Add your credentials in Apps → LogesTechs Settings.',
                'villages' => [],
            ], 422);
        } catch (\Throwable $e) {
            Log::error('LogesTechs village lookup failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Could not load districts from LogesTechs.',
                'villages' => [],
            ], 502);
        }

        return response()->json(['villages' => $villages]);
    }
}
