<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The partner area proper is for approved partners. Anyone else with the
 * role (rejected, suspended) is sent to the status page instead.
 *
 * `partner.approved:pending` also lets a pending applicant in, so they can
 * look around and track their application. Money-moving routes leave the
 * parameter off and stay approved-only.
 */
class EnsurePartnerApproved
{
    public function handle(Request $request, Closure $next, string ...$also): Response
    {
        $partner = $request->user()?->partner;

        if (! $partner) {
            abort(403, 'You are not a partner.');
        }

        if (! $partner->isApproved() && ! ($partner->status === 'pending' && in_array('pending', $also, true))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your partner account is not active.'], 403);
            }

            return redirect()->route('partners.pending');
        }

        return $next($request);
    }
}
