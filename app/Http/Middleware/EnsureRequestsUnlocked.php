<?php

namespace App\Http\Middleware;

use App\Support\ScreenLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the Requests page's endpoints until the PIN has been entered.
 *
 * As with the recycle bin and the WhatsApp inbox, the lock lives on the API:
 * the queues and the approve and reject routes are plain JSON that anyone
 * with the permission could call directly. Each request that gets through
 * pushes the expiry back, so the page only locks once it has sat idle for
 * config('locks.requests_unlock_ttl').
 */
class EnsureRequestsUnlocked
{
    public const SESSION_KEY = 'requests.unlocked_at';

    public function handle(Request $request, Closure $next): Response
    {
        // Switched off in .env (LOCK_REQUESTS=false).
        if (! ScreenLock::enabled(ScreenLock::REQUESTS)) {
            return $next($request);
        }

        $unlockedAt = $request->session()->get(self::SESSION_KEY);

        if ($unlockedAt === null) {
            return $this->locked('Enter the PIN to open the requests.');
        }

        $ttl = (int) config('locks.requests_unlock_ttl');

        if (now()->diffInMinutes($unlockedAt, absolute: true) >= $ttl) {
            $request->session()->forget(self::SESSION_KEY);

            return $this->locked('Your requests session expired. Enter the PIN again.');
        }

        $request->session()->put(self::SESSION_KEY, now());

        return $next($request);
    }

    /**
     * 423 Locked rather than 403, so the page drops back to the PIN prompt
     * instead of showing a permissions error.
     */
    private function locked(string $message): Response
    {
        return response()->json(['message' => $message], Response::HTTP_LOCKED);
    }
}
