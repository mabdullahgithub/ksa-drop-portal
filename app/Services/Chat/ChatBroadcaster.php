<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatEvent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pushes chat updates to the browsers that should get them.
 *
 * Channels (authorised in routes/channels.php):
 *  - chat.{id}       presence: the client and the team on that conversation.
 *                    Client-safe payloads only, plus typing whispers.
 *  - chat.{id}.team  private: the team only. Every message, notes included,
 *                    with the team's payload.
 *  - chat.user.{id}  private: one person's inbox (list order, unread counts).
 *
 * A push is sent only once the transaction it belongs to has committed, and
 * a failure never fails what caused it: browsers that miss one catch up on
 * the next poll or reconnect. With no websocket server configured (the
 * `log` / `null` broadcasters) nothing is sent at all: the log broadcaster
 * would write message text to the log file.
 */
class ChatBroadcaster
{
    public static function enabled(): bool
    {
        return ! in_array(config('broadcasting.default'), [null, 'log', 'null'], true);
    }

    public function messageCreated(ChatMessage $message): void
    {
        $conversation = $message->conversation;
        $message->loadMissing(['sender', 'attachments']);
        $conversation->loadMissing(ChatPresenter::relations(true));

        $this->send([$this->team($conversation)], 'message.created', [
            'message' => ChatPresenter::message($message, true),
        ]);

        if (! $message->internal) {
            $this->send([$this->room($conversation)], 'message.created', [
                'message' => ChatPresenter::message($message, false),
            ]);
        }

        if (! $message->isEvent()) {
            $this->inbox($conversation, [
                'message' => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_type' => $message->sender_type,
                    'kind' => $message->kind,
                    'internal' => $message->internal,
                ],
            ], clientToo: ! $message->internal);
        }
    }

    /** Status or details changed. */
    public function conversationUpdated(ChatConversation $conversation): void
    {
        $conversation->loadMissing(ChatPresenter::relations(true));

        $this->send([$this->team($conversation)], 'conversation.updated', [
            'conversation' => ChatPresenter::conversation($conversation, true),
        ]);
        $this->send([$this->room($conversation)], 'conversation.updated', [
            'conversation' => ChatPresenter::conversation($conversation, false),
        ]);

        $this->inbox($conversation, [], clientToo: true);
    }

    /** Someone read up to a message: the other side's "Seen" mark, and the reader's other tabs. */
    public function read(ChatConversation $conversation, int $readerId, bool $byClient, int $upTo): void
    {
        $channel = $byClient ? $this->team($conversation) : $this->room($conversation);

        $this->send([$channel], 'messages.read', [
            'conversation_id' => $conversation->id,
            'by' => $byClient ? 'client' : 'team',
            'up_to' => $upTo,
        ]);

        $this->send([new PrivateChannel("chat.user.{$readerId}")], 'inbox.read', [
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * The inbox of every agent who may see the conversation (with the team
     * payload) and of the client (with theirs).
     */
    private function inbox(ChatConversation $conversation, array $extra, bool $clientToo): void
    {
        $audience = ChatVisibility::audience($conversation)->pluck('id')->all();

        $team = array_map(fn (int $id) => new PrivateChannel("chat.user.{$id}"), $audience);
        if ($team !== []) {
            $this->send($team, 'inbox.updated', ['conversation' => ChatPresenter::conversation($conversation, true)] + $extra);
        }

        $clientUserId = $conversation->client?->user_id;
        if ($clientToo && $clientUserId) {
            $this->send([new PrivateChannel("chat.user.{$clientUserId}")], 'inbox.updated', [
                'conversation' => ChatPresenter::conversation($conversation, false),
            ] + $extra);
        }
    }

    private function room(ChatConversation $conversation): PresenceChannel
    {
        return new PresenceChannel("chat.{$conversation->id}");
    }

    private function team(ChatConversation $conversation): PrivateChannel
    {
        return new PrivateChannel("chat.{$conversation->id}.team");
    }

    private function send(array $channels, string $name, array $payload): void
    {
        if (! self::enabled() || $channels === []) {
            return;
        }

        DB::afterCommit(function () use ($channels, $name, $payload) {
            try {
                broadcast(new ChatEvent($channels, $name, $payload));
            } catch (\Throwable $e) {
                // Ids only: never the message text.
                Log::warning('chat.broadcast_failed', [
                    'event' => $name,
                    'conversation_id' => $payload['conversation']['id'] ?? $payload['message']['conversation_id'] ?? $payload['conversation_id'] ?? null,
                    'error' => $e::class,
                ]);
            }
        });
    }
}
