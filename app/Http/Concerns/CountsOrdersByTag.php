<?php

namespace App\Http\Concerns;

use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Counts orders per tag for a already-filtered orders query, in one pass for
 * all tags. Shared by the admin orders statistics and the client portal's so
 * both tag-card rows are built the same way.
 */
trait CountsOrdersByTag
{
    /**
     * Order count per tag for the given query, in one query for all tags.
     */
    protected function tagCounts($query): array
    {
        $tags = Tag::orderBy('created_at')->get(['id', 'name', 'color']);

        if ($tags->isEmpty()) {
            return [];
        }

        // Same test as whereJsonContains('tags', $name), as a column expression.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $contains = $sqlite
            ? 'exists (select 1 from json_each(orders.tags) where json_each.value = ?)'
            : 'json_contains(orders.tags, ?)';

        $columns = [];
        $bindings = [];
        foreach ($tags as $i => $tag) {
            $columns[] = "coalesce(sum(case when {$contains} then 1 else 0 end), 0) as t{$i}";
            $bindings[] = $sqlite ? $tag->name : json_encode($tag->name);
        }

        $counts = (array) $query->toBase()->selectRaw(implode(', ', $columns), $bindings)->first();

        return $tags->values()->map(fn ($tag, $i) => [
            'id'    => $tag->id,
            'name'  => $tag->name,
            'color' => $tag->color,
            'count' => (int) $counts["t{$i}"],
        ])->all();
    }
}
