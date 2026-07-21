<?php

namespace App\Http\Concerns;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves which User's notifications the current request should see — the
 * impersonated client's user when an admin is impersonating, otherwise the
 * logged-in user. Shared between the notifications API and the Inertia
 * shared-prop count so both always agree on whose count is being shown.
 */
trait ResolvesNotifiableUser
{
    protected function resolveNotifiableUser(Request $request): ?User
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($request->session()->has('impersonate.admin_id')) {
            $client = Client::find($request->session()->get('impersonate.client_id'));

            if ($client?->user_id) {
                return User::find($client->user_id) ?? $user;
            }
        }

        return $user;
    }

    /**
     * Unread count for the resolved user, cached briefly so multiple open
     * tabs / rapid navigations share one query instead of each hitting the
     * database. Callers that mutate notifications must forget this key.
     */
    protected function cachedUnreadCount(User $user): int
    {
        return Cache::remember(
            self::unreadCountCacheKey($user->id),
            now()->addSeconds(20),
            fn () => $user->unreadNotifications()->count(),
        );
    }

    protected static function unreadCountCacheKey(int $userId): string
    {
        return "notifications:unread_count:user:{$userId}";
    }

    protected function forgetUnreadCount(User $user): void
    {
        Cache::forget(self::unreadCountCacheKey($user->id));
    }
}
