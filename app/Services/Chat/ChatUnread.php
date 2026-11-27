<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\Client;
use App\Models\User;
use App\Support\ClientAccess;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read state: how far each person has read, what is still unread, and the
 * numbers on the sidebar.
 *
 * Counted live on every call, never cached, so a badge is never behind.
 * Each count is one indexed query.
 */
class ChatUnread
{
    /**
     * Add `unread_count` to an inbox query.
     *
     * The team counts what the client and other agents wrote (notes
     * included). The client counts the team's visible messages only. Events
     * never count.
     */
    public static function withCounts(Builder $query, int $readerId, bool $forTeam): Builder
    {
        return $query->withCount(['messages as unread_count' => fn (Builder $messages) => self::unreadMessages($messages, $readerId, $forTeam)]);
    }

    /**
     * The sidebar number for someone on the team: conversations not closed,
     * of clients they may see, with something from the client they have not
     * read.
     */
    public static function staffTotal(User $user): int
    {
        if (! $user->can('view chats')) {
            return 0;
        }

        $query = ChatConversation::query()
            ->where('status', '!=', ChatConversation::CLOSED)
            ->whereHas('messages', fn (Builder $messages) => self::unreadMessages($messages, $user->id, true)
                ->where('sender_type', ChatMessage::FROM_CLIENT));

        if (($ids = ClientAccess::idsFor($user)) !== null) {
            $query->whereIn('client_id', $ids);
        }

        return $query->count();
    }

    /** The sidebar number for a client: conversations with a reply they have not read. */
    public static function clientTotal(Client $client): int
    {
        return ChatConversation::query()
            ->where('client_id', $client->id)
            ->whereHas('messages', fn (Builder $messages) => self::unreadMessages($messages, (int) $client->user_id, false)
                ->where('sender_type', ChatMessage::FROM_STAFF))
            ->count();
    }

    /**
     * Mark everything up to $messageId read for this person. Never moves
     * backwards, so an old tab cannot un-read a conversation.
     */
    public static function markRead(ChatConversation $conversation, int $userId, int $messageId): void
    {
        $read = ChatRead::firstOrNew(['conversation_id' => $conversation->id, 'user_id' => $userId]);

        if ($read->exists && $read->last_read_message_id >= $messageId) {
            return;
        }

        $read->forceFill(['last_read_message_id' => $messageId, 'read_at' => now()])->save();
    }

    /** The highest message id the client's sign-in has read: the "Seen" mark the team sees. */
    public static function clientReadUpTo(ChatConversation $conversation): int
    {
        $userId = $conversation->client?->user_id;

        return $userId === null ? 0 : (int) ChatRead::where('conversation_id', $conversation->id)->where('user_id', $userId)->value('last_read_message_id');
    }

    /** The highest message id anyone on the team has read: the "Seen" mark the client sees. */
    public static function teamReadUpTo(ChatConversation $conversation): int
    {
        $clientUserId = $conversation->client?->user_id;

        return (int) ChatRead::where('conversation_id', $conversation->id)
            ->when($clientUserId, fn ($q) => $q->where('user_id', '!=', $clientUserId))
            ->max('last_read_message_id');
    }

    private static function unreadMessages(Builder $messages, int $readerId, bool $forTeam): Builder
    {
        $messages
            ->where('kind', '!=', ChatMessage::EVENT)
            ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $readerId))
            ->whereRaw(
                'chat_messages.id > coalesce((select cr.last_read_message_id from chat_reads cr where cr.conversation_id = chat_messages.conversation_id and cr.user_id = ?), 0)',
                [$readerId],
            );

        if (! $forTeam) {
            $messages->where('internal', false);
        }

        return $messages;
    }
}
