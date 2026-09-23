<?php

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared list / restore / purge mechanics for the recycle bin.
 *
 * Every entity in the bin differs only in which model it queries and how a row
 * is shaped for the table, so the paging, sorting, id handling and chunked
 * purge live here once.
 */
trait InteractsWithTrashedRecords
{
    /**
     * Ids from a request body, normalised. The bin never uses route-model
     * binding: implicit binding applies SoftDeletingScope (and, for orders,
     * the shopify_visible scope), so a bound trashed model always 404s.
     *
     * @return array<int, int>
     */
    protected function trashedIds(Request $request, string $key = 'ids'): array
    {
        $request->validate([
            $key => 'required|array|min:1',
            $key . '.*' => 'integer',
        ]);

        return array_values(array_unique(array_filter(
            array_map('intval', $request->input($key))
        )));
    }

    /**
     * Apply a validated sort to a trashed listing.
     *
     * $allowed is an allow-list rather than a raw column pass-through: the
     * inventory listing sorts inside a fromSub(), where an arbitrary
     * user-supplied column string would be interpolated straight into SQL.
     *
     * @param  array<int, string>  $allowed
     */
    protected function applyTrashedSort(Request $request, $query, array $allowed, string $default = 'deleted_at')
    {
        $column = (string) $request->input('sort_by', $default);
        $direction = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($column, $allowed, true)) {
            $column = $default;
        }

        return $query->orderBy($column, $direction);
    }

    /**
     * Permanently delete the given trashed models, one at a time.
     *
     * Deliberately not $query->forceDelete(): that issues a single mass DELETE
     * and fires no model events, so none of the file-cleanup observers would
     * run and uploaded images would be orphaned on disk. chunkById keeps
     * "empty the bin" from loading an unbounded result set into memory.
     */
    protected function purgeTrashed(Builder $query): int
    {
        $purged = 0;

        $query->chunkById(200, function ($models) use (&$purged) {
            DB::transaction(function () use ($models, &$purged) {
                foreach ($models as $model) {
                    $model->forceDelete();
                    $purged++;
                }
            });
        });

        return $purged;
    }

    /**
     * Per-page size, clamped so a caller cannot ask for the whole table.
     */
    protected function trashedPerPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 25), 1), 100);
    }
}
