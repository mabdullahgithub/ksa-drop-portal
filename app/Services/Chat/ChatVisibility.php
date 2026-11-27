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
     * @return Collection<int, User>
     */
    public static function audience(ChatConversation $conversation): Collection
    {
        return self::agents()
            ->filter(fn (User $user) => self::canSee($user, $conversation))
            ->values();
    }

    /**
     * Everyone on the team who can open the inbox: those holding `view chats`
     * through a role, and the full-access roles that pass every gate.
     *
     * @return Collection<int, User>
     */
    public static function agents(): Collection
    {
        $holders = rescue(fn () => User::permission('view chats')->get(), new Collection, false);
        $full = rescue(fn () => User::role(User::FULL_ACCESS_ROLES)->get(), new Collection, false);

        return $holders->merge($full)->unique('id')->values();
    }
}
