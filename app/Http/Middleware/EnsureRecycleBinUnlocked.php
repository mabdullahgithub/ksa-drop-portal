<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the recycle bin's data endpoints until the PIN has been entered.
 *
 * Gating only the page would be decorative: the listing, restore and purge
 * endpoints are ordinary JSON routes that anyone signed in with the delete
 * permission could call directly. The lock has to live here to mean anything.
 */
class EnsureRecycleBinUnlocked
{
    public const SESSION_KEY = 'recycle_bin.unlocked_at';

    public function handle(Request $request, Closure $next): Response
    {
        $unlockedAt = $request->session()->get(self::SESSION_KEY);

        if ($unlockedAt === null) {
            return $this->locked('Enter the recycle bin PIN to continue.');
        }

        $ttl = (int) config('recyclebin.unlock_ttl');

        if (now()->diffInMinutes($unlockedAt, absolute: true) >= $ttl) {
            $request->session()->forget(self::SESSION_KEY);

            return $this->locked('Your recycle bin session expired. Enter the PIN again.');
        }

        return $next($request);
    }

    /**
     * 423 Locked rather than 403: the caller has the right permission, the
     * screen just is not unlocked. The frontend keys off this status to drop
     * back to the PIN prompt instead of showing a permissions error.
     */
    private function locked(string $message): Response
    {
        return response()->json(['message' => $message], Response::HTTP_LOCKED);
    }
}
