<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One live chat update, sent straight to the websocket server.
 *
 * ShouldBroadcastNow, not ShouldBroadcast: the one queue worker keeps
 * Shopify jobs in order, and a chat message must not wait behind them.
 * Built only by ChatBroadcaster, which picks the channels and the payload
 * for each audience.
 */
class ChatEvent implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<Channel>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $channels,
        public string $name,
        public array $payload,
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return $this->channels;
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
