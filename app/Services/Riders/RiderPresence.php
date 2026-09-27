<?php

namespace App\Services\Riders;

use App\Models\Rider;
use Illuminate\Support\Facades\Cache;

/**
 * Who has the rider app open right now — the green dot on the Riders page.
 *
 * The app checks in every minute while it's on screen. A check-in counts for
 * WINDOW seconds, so a rider who closes the app, locks the phone or loses
 * signal drops off on their own; a quick switch to WhatsApp or Maps doesn't.
 * Kept in the cache, not the database: it's a minute-by-minute signal.
 */
class RiderPresence
{
    public const WINDOW = 120;

    public static function checkIn(Rider $rider): void
    {
        Cache::put(self::key($rider->id), now()->timestamp, self::WINDOW);
    }

    /** Signed out, suspended or removed: off the list straight away. */
    public static function forget(Rider $rider): void
    {
        Cache::forget(self::key($rider->id));
    }

    /**
     * @param  array<int>  $riderIds
     * @return array<int> the ones online now
     */
    public static function online(array $riderIds): array
    {
        if ($riderIds === []) {
            return [];
        }

        $keys = array_map(self::key(...), $riderIds);
        $seen = Cache::many($keys);

        return array_values(array_filter($riderIds, fn (int $id) => $seen[self::key($id)] !== null));
    }

    public static function isOnline(Rider $rider): bool
    {
        return Cache::has(self::key($rider->id));
    }

    private static function key(int $riderId): string
    {
        return "rider-online:{$riderId}";
    }
}
