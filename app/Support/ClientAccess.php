<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which clients the person making this request may see.
 *
 * Off unless RestrictToAssignedClients switches it on for a staff request, so
 * queue jobs, webhooks, the rider app, console commands and the client portal
 * always see everything. While on, the models using RestrictedByClientAccess
 * only return rows belonging to these clients.
 */
class ClientAccess
{
    /** @var list<int>|null null = no restriction */
    private ?array $ids = null;

    /**
     * The clients a user is limited to, or null when they may see every
     * client. Full-access roles and client accounts are never limited.
     *
     * Someone on "all dropshippers" or "all fulfilment" gets every client of
     * that type as it stands on this request, so a client added later is
     * theirs without anyone assigning it.
     *
     * @return list<int>|null
     */
    public static function idsFor(?User $user): ?array
    {
        $type = $user?->clientAccessType();

        if (! $user || ($type === null && $user->client_access !== User::CLIENT_ACCESS_ASSIGNED)) {
            return null;
        }

        if ($user->hasFullAccess() || $user->hasRole('client')) {
            return null;
        }

        $ids = DB::table('client_user_access')->where('user_id', $user->id)->pluck('client_id');

        if ($type !== null) {
            $ids = $ids->merge(DB::table('clients')->whereJsonContains('client_types', $type)->pluck('id'));
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** @param  list<int>|null  $ids */
    public function restrictTo(?array $ids): void
    {
        $this->ids = $ids === null ? null : array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return list<int>|null */
    public function ids(): ?array
    {
        return $this->ids;
    }

    public function restricted(): bool
    {
        return $this->ids !== null;
    }

    public function allows(?int $clientId): bool
    {
        return $this->ids === null || ($clientId !== null && in_array($clientId, $this->ids, true));
    }

    /** Limit a raw `orders` query the model scope cannot reach. */
    public function scopeOrders($query, string $column = 'client_id')
    {
        return $this->ids === null ? $query : $query->whereIn($column, $this->ids);
    }
}
