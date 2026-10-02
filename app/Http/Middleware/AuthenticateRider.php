<?php

namespace App\Http\Middleware;

use App\Models\RiderDevice;
use App\Services\Riders\RiderAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs the rider app's API requests in from the device cookie.
 *
 * Checked on every request, so suspending a rider or signing their phone out
 * from the portal takes effect on the very next tap.
 */
class AuthenticateRider
{
    public function __construct(private RiderAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->auth->deviceForToken($request->cookie(RiderAuthService::COOKIE));

        if ($problem = $this->auth->deviceProblem($device)) {
            $response = response()->json([
                'message' => $this->message($problem),
                'code' => $problem,
            ], $problem === 'suspended' ? 403 : 401);

            // Keep a suspended rider's cookie: reactivating them should just
            // work, without a new link.
            return $problem === 'suspended' ? $response : $response->withCookie($this->auth->forgetCookie());
        }

        $this->touch($device, $request);

        $request->attributes->set('rider', $device->rider);
        $request->attributes->set('rider_device', $device);

        return $next($request);
    }

    /**
     * "Last seen" for the Riders page, written at most every 5 minutes.
     */
    private function touch(RiderDevice $device, Request $request): void
    {
        if ($device->last_seen_at && $device->last_seen_at->gt(now()->subMinutes(5))) {
            return;
        }

        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        $device->rider->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    private function message(string $problem): string
    {
        return match ($problem) {
            'replaced' => 'You signed in on another phone.',
            'revoked' => 'You were signed out by your supervisor.',
            'suspended' => 'Your account is suspended. Contact your supervisor.',
            default => 'Please sign in.',
        };
    }
}
