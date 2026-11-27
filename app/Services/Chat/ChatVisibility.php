<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\User;
use App\Support\ClientAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who on the team may see which conversation. The inbox, the channel
 * authorisation and the notifier all ask here, so the three never disagree.
 *
 * Two things must hold: `view chats`, and the conversation's client is one
 * the person may see (client access, isolated clients included).
 *
 * Self-contained on purpose: it does not lean on the request-level client
 * scope, so it gives the same answer in a channel auth request, a queue job
 * or a console command.
 */
class ChatVisibility
{
    public static function canSee(User $user, ChatConversation $conversation): bool
    {
        if ($user->hasRole('client') || ! $user->can('view chats')) {
            return false;
        }

        return self::canSeeClient($user, $conversation->client_id);
    }

    /** The client owns it: only their own sign-in. */
    public static function isOwner(User $user, ChatConversation $conversation): bool
    {
        return $user->hasRole('client')
            && $user->client !== null
            && $user->client->id === $conversation->client_id;
    }

    /**
     * Client access for one client, worked out for this user rather than
     * for whoever is signed in on the request.
     */
    public static function canSeeClient(User $user, int $clientId): bool
    {
        $isolated = (bool) DB::table('clients')->where('id', $clientId)->value('is_isolated');

        if ($isolated) {
            // Only staff assigned to it by name, whatever else they hold.
            return DB::table('client_user_access')->where('user_id', $user->id)->where('client_id', $clientId)->exists();
        }

        $ids = ClientAccess::idsFor($user);

        return $ids === null || in_array($clientId, $ids, true);
    }

    /**
     * Every team member who may see this conversation: who to tell about it
     * and whose inbox to update.
     *
     * The same rules as canSee(), asked for everyone at once: it runs on
     * every message, so its queries must not grow with the size of the team.
     * Nothing is remembered between calls, so a change of access applies to
     * the very next message.
     *
     * @return Collection<int, User>
     */
    public static function audience(ChatConversation $conversation): Collection
    {
        $agents = self::agents()
            ->filter(fn (User $user) => ! $user->hasRole('client') && $user->can('view chats'));

        $client = DB::table('clients')->where('id', $conversation->client_id)->first(['is_isolated', 'client_types']);
        $isolated = (bool) ($client->is_isolated ?? false);
        $types = (array) json_decode($client->client_types ?? '[]', true);

        // Whose list of assigned clients includes this one.
        $assigned = DB::table('client_user_access')
            ->where('client_id', $conversation->client_id)
            ->whereIn('user_id', $agents->pluck('id'))
            ->pluck('user_id')
            ->flip();

        return $agents->filter(function (User $user) use ($isolated, $types, $assigned) {
            if ($isolated) {
                return $assigned->has($user->id);
            }

            $type = $user->clientAccessType();
            $limited = $type !== null || $user->client_access === User::CLIENT_ACCESS_ASSIGNED;

            return ! $limited
                || $user->hasFullAccess()
                || $assigned->has($user->id)
                || ($type !== null && in_array($type, $types, true));
        })->values();
    }

    /**
     * Everyone on the team who can open the inbox: those holding `view chats`
     * through a role, and the full-access roles that pass every gate.
     *
     * @return Collection<int, User>
     */
    public static function agents(): Collection
    {
        // Roles and permissions come along, so asking each agent what they may
        // do costs no further queries.
        $with = ['roles.permissions', 'permissions'];
        $holders = rescue(fn () => User::permission('view chats')->with($with)->get(), new Collection, false);
        $full = rescue(fn () => User::role(User::FULL_ACCESS_ROLES)->with($with)->get(), new Collection, false);

        return $holders->merge($full)->unique('id')->values();
    }
}
