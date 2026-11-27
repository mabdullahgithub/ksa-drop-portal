<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything that changes a conversation other than a message: opening one,
 * its status, its details, closing old ones, deleting.
 *
 * Each change writes an `event` line into the timeline, with who made it, so
 * the conversation is its own audit trail: who resolved it, when the
 * priority went up. Events the client should not see are `internal`.
 */
class ChatWorkflow
{
    /** Status => where the team may move it from there. */
    public const TEAM_MOVES = [
        ChatConversation::OPEN => [ChatConversation::PENDING, ChatConversation::ON_HOLD, ChatConversation::RESOLVED, ChatConversation::CLOSED],
        ChatConversation::PENDING => [ChatConversation::OPEN, ChatConversation::ON_HOLD, ChatConversation::RESOLVED, ChatConversation::CLOSED],
        ChatConversation::ON_HOLD => [ChatConversation::OPEN, ChatConversation::PENDING, ChatConversation::RESOLVED, ChatConversation::CLOSED],
        ChatConversation::RESOLVED => [ChatConversation::OPEN, ChatConversation::CLOSED],
        ChatConversation::CLOSED => [ChatConversation::OPEN],
    ];

    /** Status => where the client may move it: they can only resolve, and that is final for them. */
    public const CLIENT_MOVES = [
        ChatConversation::OPEN => [ChatConversation::RESOLVED],
        ChatConversation::PENDING => [ChatConversation::RESOLVED],
        ChatConversation::ON_HOLD => [ChatConversation::RESOLVED],
    ];

    /** Status changes the client is shown in their timeline. */
    private const CLIENT_SEES = [ChatConversation::RESOLVED, ChatConversation::CLOSED];

    public function __construct(
        private ChatBroadcaster $broadcaster,
        private ChatNotifier $notifier,
    ) {}

    /**
     * A new conversation with its first message.
     *
     * @param  array{subject: string, category: string, priority?: string, order_id?: ?int, body?: ?string, client_uuid?: ?string, files?: array}  $input
     */
    public function open(Client $client, User $by, string $openedBy, array $input): ChatConversation
    {
        $conversation = DB::transaction(function () use ($client, $by, $openedBy, $input) {
            $byStaff = $openedBy === ChatConversation::BY_STAFF;

            $conversation = ChatConversation::create([
                'client_id' => $client->id,
                'order_id' => $this->orderOf($client, $input['order_id'] ?? null),
                'subject' => $input['subject'],
                'category' => $input['category'],
                'priority' => $byStaff ? ($input['priority'] ?? 'normal') : 'normal',
                'status' => ChatConversation::OPEN,
                'opened_by' => $openedBy,
                'created_by' => $by->id,
                'last_message_at' => now(),
            ]);

            app(ChatMessenger::class)->send($conversation, $by, $byStaff ? ChatMessage::FROM_STAFF : ChatMessage::FROM_CLIENT, [
                'kind' => ChatMessage::MESSAGE,
                'body' => $input['body'] ?? null,
                'client_uuid' => $input['client_uuid'] ?? null,
                'files' => $input['files'] ?? [],
                'orders' => $input['orders'] ?? [],
                'products' => $input['products'] ?? [],
                'origin' => $input['origin'] ?? null,
            ], opening: true);

            return $conversation->fresh();
        });

        $this->notifier->started($conversation, $by);

        return $conversation;
    }

    public function changeStatus(ChatConversation $conversation, User $by, string $to, bool $asClient = false): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $by, $to, $asClient) {
            $held = $this->lock($conversation);
            $from = $held->status;

            if ($from === $to) {
                return $held;
            }

            $moves = $asClient ? self::CLIENT_MOVES : self::TEAM_MOVES;
            if (! in_array($to, $moves[$from] ?? [], true)) {
                abort(422, "A {$this->label($from)} chat can't be moved to {$this->label($to)}.");
            }

            $this->applyStatus($held, $to, $by);
            $held->save();

            $reopened = $to === ChatConversation::OPEN && in_array($from, [ChatConversation::RESOLVED, ChatConversation::CLOSED], true);

            $event = $this->recordEvent($held, $by, $asClient ? ChatMessage::FROM_CLIENT : ChatMessage::FROM_STAFF, [
                'event' => 'status',
                'from' => $from,
                'to' => $to,
            ], internal: ! $reopened && ! in_array($to, self::CLIENT_SEES, true));

            $this->broadcaster->messageCreated($event);
            $this->broadcaster->conversationUpdated($held);

            if ($to === ChatConversation::RESOLVED && ! $asClient) {
                $this->notifier->resolved($held, $by);
            }

            return $held;
        });
    }

    /** @param  array{subject?: string, category?: string, priority?: string, order_id?: ?int}  $input */
    public function updateDetails(ChatConversation $conversation, User $by, array $input): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $by, $input) {
            $held = $this->lock($conversation);

            if (array_key_exists('order_id', $input)) {
                $input['order_id'] = $this->orderOf($held->client, $input['order_id']);
            }

            $changes = [];
            foreach (['subject', 'category', 'priority', 'order_id'] as $field) {
                if (array_key_exists($field, $input) && $held->{$field} !== $input[$field]) {
                    $changes[$field] = [$held->{$field}, $input[$field]];
                    $held->{$field} = $input[$field];
                }
            }

            if ($changes === []) {
                return $held;
            }

            if (isset($changes['order_id'])) {
                $changes['order_id'] = [
                    Order::whereKey($changes['order_id'][0])->value('order_number'),
                    Order::whereKey($changes['order_id'][1])->value('order_number'),
                ];
            }

            $held->save();
            $held->unsetRelation('order');

            $event = $this->recordEvent($held, $by, ChatMessage::FROM_STAFF, [
                'event' => 'details',
                'changes' => $changes,
            ], internal: true);

            $this->broadcaster->messageCreated($event);
            $this->broadcaster->conversationUpdated($held);

            return $held;
        });
    }

    /**
     * Close conversations that were resolved long enough ago. They are
     * already locked; this files them as closed.
     */
    public function autoClose(): int
    {
        $closed = 0;

        ChatConversation::acrossClients()
            ->where('status', ChatConversation::RESOLVED)
            ->where('resolved_at', '<=', now()->subDays(config('chat.auto_close_days')))
            ->orderBy('id')
            ->each(function (ChatConversation $conversation) use (&$closed) {
                DB::transaction(function () use ($conversation, &$closed) {
                    $held = ChatConversation::acrossClients()->whereKey($conversation->id)->lockForUpdate()->first();
                    if (! $held || $held->status !== ChatConversation::RESOLVED) {
                        return;
                    }

                    $held->forceFill(['status' => ChatConversation::CLOSED, 'closed_at' => now()])->save();

                    $event = $this->recordEvent($held, null, ChatMessage::FROM_SYSTEM, [
                        'event' => 'status',
                        'from' => ChatConversation::RESOLVED,
                        'to' => ChatConversation::CLOSED,
                        'auto' => true,
                    ], internal: false);

                    $this->broadcaster->messageCreated($event);
                    $this->broadcaster->conversationUpdated($held);
                    $closed++;
                });
            });

        return $closed;
    }

    /** One line in the timeline. */
    public function recordEvent(ChatConversation $conversation, ?User $by, string $senderType, array $meta, bool $internal): ChatMessage
    {
        $event = $conversation->messages()->create(ChatMessage::senderSnapshot($by, $senderType) + [
            'sender_type' => $senderType,
            'kind' => ChatMessage::EVENT,
            'internal' => $internal,
            'meta' => $meta + ['by' => $by?->name],
        ]);

        $event->setRelation('conversation', $conversation);
        $event->setRelation('sender', $by);

        return $event;
    }

    /** Set the timestamps that go with a status, on an already locked row. */
    public function applyStatus(ChatConversation $conversation, string $to, ?User $by): void
    {
        $from = $conversation->status;
        $conversation->status = $to;

        match ($to) {
            ChatConversation::RESOLVED => $conversation->forceFill(['resolved_at' => now(), 'resolved_by' => $by?->id, 'closed_at' => null]),
            ChatConversation::CLOSED => $conversation->forceFill(['closed_at' => now()]),
            default => in_array($from, [ChatConversation::RESOLVED, ChatConversation::CLOSED], true)
                ? $conversation->forceFill([
                    'resolved_at' => null,
                    'resolved_by' => null,
                    'closed_at' => null,
                    'reopened_count' => $conversation->reopened_count + 1,
                ])
                : null,
        };
    }

    private function lock(ChatConversation $conversation): ChatConversation
    {
        $held = ChatConversation::acrossClients()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
        $held->setRelation('client', $conversation->relationLoaded('client') ? $conversation->client : $held->client()->first());

        return $held;
    }

    /** An order id, only if it is this client's. */
    private function orderOf(?Client $client, mixed $orderId): ?int
    {
        if ($orderId === null || $orderId === '' || ! $client) {
            return null;
        }

        $order = Order::whereKey($orderId)->where('client_id', $client->id)->value('id');

        if ($order === null) {
            abort(422, 'That order is not one of this client\'s.');
        }

        return (int) $order;
    }

    private function label(string $status): string
    {
        return str_replace('_', ' ', $status);
    }
}
