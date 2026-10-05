<?php

namespace App\Http\Middleware;

use App\Models\Rider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps each half of the rider app to its own people: riders take and
 * deliver parcels, inventory managers scan them OUT and IN. Runs after
 * AuthenticateRider, which puts the signed-in rider on the request.
 */
class EnsureRiderRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var Rider|null $rider */
        $rider = $request->attributes->get('rider');

        if ($rider?->role !== $role) {
            // The office changed their role while the app was open: the app
            // reloads into the other half when it sees this code.
            return response()->json([
                'message' => 'This is not available for your account.',
                'code' => 'wrong_role',
            ], 403);
        }

        return $next($request);
    }
}
