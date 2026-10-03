<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the WhatsApp inbox's conversation endpoints until the PIN has been
 * entered.
 *
 * As with the recycle bin, the lock lives on the API because the list, thread
 * and reply routes are plain JSON that anyone with 'view whatsapp' could call
 * directly. Each request that gets through pushes the expiry back, so the
 * inbox only locks once it has sat idle for config('whatsapp.unlock_ttl').
 */
class EnsureWhatsAppUnlocked
{
    public const SESSION_KEY = 'whatsapp.unlocked_at';

    public function handle(Request $request, Closure $next): Response
    {
        $unlockedAt = $request->session()->get(self::SESSION_KEY);

        if ($unlockedAt === null) {
            return $this->locked('Enter the WhatsApp PIN to continue.');
        }

        $ttl = (int) config('whatsapp.unlock_ttl');

        if (now()->diffInMinutes($unlockedAt, absolute: true) >= $ttl) {
            $request->session()->forget(self::SESSION_KEY);

            return $this->locked('Your WhatsApp session expired. Enter the PIN again.');
        }

        $request->session()->put(self::SESSION_KEY, now());

        return $next($request);
    }

    /**
     * 423 Locked rather than 403, so the inbox drops back to the PIN prompt
     * instead of showing a permissions error.
     */
    private function locked(string $message): Response
    {
        return response()->json(['message' => $message], Response::HTTP_LOCKED);
    }
}
