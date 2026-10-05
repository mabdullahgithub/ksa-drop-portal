<?php

namespace App\Services\Inventory;

use App\Models\StockScan;
use App\Services\Riders\RiderDayStats;

/**
 * What each inventory manager scanned "today" in the business timezone —
 * their app's home screen and the portal's Riders page show the same figures.
 */
class StockDayStats
{
    /**
     * @param  array<int>  $managerIds
     * @return array<int, array{out: array{parcels: int, pieces: int}, in: array{parcels: int, pieces: int}}>
     */
    public static function forManagers(array $managerIds): array
    {
        $stats = [];
        foreach ($managerIds as $id) {
            $stats[$id] = [
                StockScan::OUT => ['parcels' => 0, 'pieces' => 0],
                StockScan::IN => ['parcels' => 0, 'pieces' => 0],
            ];
        }

        if ($managerIds === []) {
            return $stats;
        }

        $rows = StockScan::whereIn('rider_id', $managerIds)
            ->where('occurred_at', '>=', RiderDayStats::startOfToday())
            ->selectRaw('rider_id, direction, COUNT(*) as parcels, COALESCE(SUM(pieces), 0) as pieces')
            ->groupBy('rider_id', 'direction')
            ->get();

        foreach ($rows as $row) {
            $stats[$row->rider_id][$row->direction] = ['parcels' => (int) $row->parcels, 'pieces' => (int) $row->pieces];
        }

        return $stats;
    }

    /**
     * @return array{out: array{parcels: int, pieces: int}, in: array{parcels: int, pieces: int}}
     */
    public static function forManager(int $managerId): array
    {
        return self::forManagers([$managerId])[$managerId];
    }
}
