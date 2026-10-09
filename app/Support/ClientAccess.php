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
     * @return list<int>|null
     */
    public static function idsFor(?User $user): ?array
    {
        if (! $user || $user->client_access !== User::CLIENT_ACCESS_ASSIGNED) {
            return null;
        }

        if ($user->hasFullAccess() || $user->hasRole('client')) {
            return null;
        }

        return DB::table('client_user_access')
            ->where('user_id', $user->id)
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
