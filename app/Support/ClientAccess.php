<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Which clients the person making this request may see.
 *
 * Off unless RestrictToAssignedClients switches it on for a staff request, so
 * queue jobs, webhooks, the rider app, console commands and the client portal
 * always see everything. While on, the models using RestrictedByClientAccess
 * only return rows belonging to these clients.
 *
 * Isolated clients (clients.is_isolated) are the exception to "everything":
 * they are left out of every query, staff, console and queue alike, except for
 * their own sign-in and staff assigned to them by name. So they never reach an
 * admin list, a stat, a report, a courier sync or a webhook lookup.
 */
class ClientAccess
{
    /** @var list<int>|null null = no restriction */
    private ?array $ids = null;

    /** @var list<int>|null every isolated client, read once */
    private ?array $isolated = null;

    /** The user $revealed was worked out for; false = not worked out yet. */
    private int|null|false $revealedFor = false;

    /** @var list<int> isolated clients this person may see */
    private array $revealed = [];

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
        if ($clientId !== null && in_array($clientId, $this->hiddenIds(), true)) {
            return false;
        }

        return $this->ids === null || ($clientId !== null && in_array($clientId, $this->ids, true));
    }

    /**
     * Isolated clients the person on this request may not see. Worked out from
     * whoever is signed in, so it holds with or without the middleware: a
     * client sees their own store, staff see the ones assigned to them by name,
     * and nobody else (console and queue included) sees any.
     *
     * @return list<int>
     */
    public function hiddenIds(): array
    {
        $this->isolated ??= rescue(
            fn () => DB::table('clients')->where('is_isolated', true)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            [],   // the column not migrated yet: nothing is isolated
            false,
        );

        if ($this->isolated === []) {
            return [];
        }

        // hasUser(), not user(): only someone already signed in on this
        // request, never a session lookup from a console run or a webhook.
        $user = Auth::hasUser() ? Auth::user() : null;
        $userId = $user instanceof User ? $user->id : null;

        if ($this->revealedFor !== $userId) {
            $this->revealedFor = $userId;
            $this->revealed = $userId === null ? [] : DB::table('clients')->where('is_isolated', true)->where('user_id', $userId)->pluck('id')
                ->merge(DB::table('client_user_access')->where('user_id', $userId)->pluck('client_id'))
                ->map(fn ($id) => (int) $id)->all();
        }

        return array_values(array_diff($this->isolated, $this->revealed));
    }

    /**
     * Sign-ins of isolated clients this person may not see, for the user
     * lists the client scope does not reach.
     *
     * @return list<int>
     */
    public function hiddenUserIds(): array
    {
        $hidden = $this->hiddenIds();

        return $hidden === [] ? [] : DB::table('clients')->whereIn('id', $hidden)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Limit a raw `orders` query the model scope cannot reach. */
    public function scopeOrders($query, string $column = 'client_id')
    {
        if (($hidden = $this->hiddenIds()) !== []) {
            $query->whereNotIn($column, $hidden);
        }

        return $this->ids === null ? $query : $query->whereIn($column, $this->ids);
    }
}
