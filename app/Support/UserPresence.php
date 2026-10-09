<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Who is using the portal right now — the green dot on the Users page.
 *
 * Every signed-in request checks the person in (see TrackUserPresence). A
 * check-in counts for WINDOW seconds, so someone who closes the tab or walks
 * away drops off on their own; signing out takes them off straight away.
 * Kept in the cache, not the database: it's a minute-by-minute signal.
 */
class UserPresence
{
    public const WINDOW = 300;

    /** A check-in younger than this is left alone, so a busy page is not a cache write per request. */
    private const REFRESH = 60;

    public static function checkIn(User $user): void
    {
        $now = now()->timestamp;
        $seen = Cache::get(self::key($user->id));

        if ($seen === null || $seen <= $now - self::REFRESH) {
            Cache::put(self::key($user->id), $now, self::WINDOW);
        }
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::key($user->id));
    }

    /**
     * @param  array<int>  $userIds
     * @return array<int> the ones online now
     */
    public static function online(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $keys = array_map(self::key(...), $userIds);
        $seen = Cache::many($keys);

        return array_values(array_filter($userIds, fn (int $id) => $seen[self::key($id)] !== null));
    }

    private static function key(int $userId): string
    {
        return "user-online:{$userId}";
    }
}
