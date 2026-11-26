<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remembers that the signed-in person has taken a product tour, so it does
 * not open by itself again on any device. Taking it again by hand still works.
 */
class TourController extends Controller
{
    /** Old tour versions fall off the front; there are only a handful of tours. */
    private const KEEP = 50;

    public function seen(Request $request, string $tour): JsonResponse
    {
        $user = $request->user();
        $seen = $user->tours_seen ?? [];

        if (! in_array($tour, $seen, true)) {
            $seen = array_slice([...$seen, $tour], -self::KEEP);
            // Not mass-assigned: only this endpoint writes the list.
            $user->forceFill(['tours_seen' => $seen])->save();
        }

        return response()->json(['tours_seen' => $seen]);
    }
}
