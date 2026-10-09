<?php

namespace App\Models\Concerns;

use App\Support\ClientAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Hides rows of clients the person may not see; see ClientAccess.
 *
 * A named global scope, so a query that must see everything opts out with
 * withoutGlobalScope('client_access'). It does nothing unless a staff request
 * switched the restriction on.
 */
trait RestrictedByClientAccess
{
    public static function bootRestrictedByClientAccess(): void
    {
        static::addGlobalScope('client_access', function (Builder $builder) {
            $ids = app(ClientAccess::class)->ids();

            if ($ids === null) {
                return;
            }

            $column = static::clientAccessColumn();
            $qualified = $builder->qualifyColumn($column);

            match ($column) {
                'order_id' => $builder->whereIn($qualified, DB::table('orders')->select('id')->whereIn('client_id', $ids)),
                'shipment_id' => $builder->whereIn($qualified, DB::table('shipments')
                    ->select('shipments.id')
                    ->join('orders', 'orders.id', '=', 'shipments.order_id')
                    ->whereIn('orders.client_id', $ids)),
                default => $builder->whereIn($qualified, $ids),
            };
        });
    }

    /**
     * Every client's rows, whoever is asking. For the few lookups that are
     * about the whole table rather than about what a person may see: the next
     * number in a sequence, or whether a code is already taken.
     */
    public function scopeAcrossClients(Builder $query): Builder
    {
        return $query->withoutGlobalScope('client_access');
    }

    /**
     * The column that leads to the client: `client_id` on the row itself,
     * `id` on the client, or `order_id` / `shipment_id` to go through those.
     */
    protected static function clientAccessColumn(): string
    {
        return 'client_id';
    }
}
