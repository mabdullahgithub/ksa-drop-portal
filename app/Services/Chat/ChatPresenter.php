<?php

namespace App\Services\Chat;

use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Conversations and messages as the browser receives them, for one of two
 * audiences. `forTeam: false` is the portal (and the client's live channel):
 * no internal notes or events, no priority, no staff emails, and file links
 * that go through the portal's own gated route.
 *
 * Every payload that leaves the server is built here, so what a client can
 * see is decided in one place.
 */
class ChatPresenter
{
    /** @return list<string|array> */
    public static function relations(bool $forTeam): array
    {
        return [
            'client:id,company_name,short_id,logo,status,client_types,user_id',
            'order:id,order_number,fulfillment_status,total,currency,client_id,created_at',
            $forTeam ? 'latestTeamMessage.attachments' : 'latestClientMessage.attachments',
        ];
    }

    public static function conversation(ChatConversation $conversation, bool $forTeam): array
    {
        $latest = $forTeam ? $conversation->latestTeamMessage : $conversation->latestClientMessage;

        $data = [
            'id' => $conversation->id,
            'reference' => $conversation->reference,
            'subject' => $conversation->subject,
            'category' => $conversation->category,
            'status' => $conversation->status,
            'opened_by' => $conversation->opened_by,
            'order' => $conversation->order ? [
                'id' => $conversation->order->id,
                'order_number' => $conversation->order->order_number,
                'status' => $conversation->order->fulfillment_status,
                'total' => $conversation->order->total,
                'currency' => $conversation->order->currency,
            ] : null,
            'last_message' => $latest ? [
                'body' => Str::limit((string) $latest->body, 140),
                'kind' => $latest->kind,
                'sender_type' => $latest->sender_type,
                // Only a file was sent: the list says "Attachment".
                'attachment_only' => blank($latest->body),
                'voice' => blank($latest->body) && $latest->attachments->contains('is_voice', true),
                // Only orders or products were sent: the list names them.
                'shared' => blank($latest->body) ? ($latest->shared_items[0]['type'] ?? null) : null,
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toIso8601String(),
            'resolved_at' => $conversation->resolved_at?->toIso8601String(),
            'closed_at' => $conversation->closed_at?->toIso8601String(),
        ];

        if (! $forTeam) {
            return $data;
        }

        return $data + [
            'priority' => $conversation->priority,
            'client' => $conversation->client ? [
                'id' => $conversation->client->id,
                'company_name' => $conversation->client->company_name,
                'short_id' => $conversation->client->short_id,
                'status' => $conversation->client->status,
                'logo_url' => $conversation->client->logo ? Storage::disk('public')->url($conversation->client->logo) : null,
                'user_id' => $conversation->client->user_id,
            ] : null,
            'first_response_at' => $conversation->first_response_at?->toIso8601String(),
            'last_client_message_at' => $conversation->last_client_message_at?->toIso8601String(),
            'reopened_count' => $conversation->reopened_count,
        ];
    }

    public static function message(ChatMessage $message, bool $forTeam): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'kind' => $message->kind,
            'internal' => $forTeam ? $message->internal : false,
            'sender_type' => $message->sender_type,
            'sender' => self::sender($message, $forTeam),
            'body' => $message->body,
            'meta' => $forTeam ? $message->meta : self::clientMeta($message->meta),
            'items' => ChatSharedItems::present($message->shared_items),
            'client_uuid' => $message->client_uuid,
            'attachments' => $message->attachments
                ->map(fn (ChatAttachment $attachment) => self::attachment($attachment, $forTeam))
                ->values()
                ->all(),
            'created_at' => $message->created_at?->toIso8601String(),
            'edited_at' => $message->edited_at?->toIso8601String(),
        ];
    }

    public static function attachment(ChatAttachment $attachment, bool $forTeam): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'mime' => $attachment->mime,
            'size' => $attachment->size,
            'is_image' => $attachment->isImage(),
            'is_voice' => (bool) $attachment->is_voice,
            'duration' => $attachment->duration_seconds,
            'width' => $attachment->width,
            'height' => $attachment->height,
            'url' => route($forTeam ? 'api.chats.attachments.show' : 'portal.api.chats.attachments.show', $attachment, absolute: false),
        ];
    }

    /**
     * Who sent a message, from what was saved with it (so it still reads
     * right after a rename or a deleted account). The team also gets the
     * account id and the role it had then.
     */
    private static function sender(ChatMessage $message, bool $forTeam): ?array
    {
        $name = $message->sender_name ?? $message->sender?->name;

        if ($name === null) {
            return null;
        }

        $sender = [
            'name' => $name,
            'avatar_url' => $message->sender?->avatar ? Storage::disk('public')->url($message->sender->avatar) : null,
        ];

        return $forTeam
            ? ['id' => $message->sender_id] + $sender + ['role' => $message->sender_role]
            : $sender;
    }

    /**
     * An event line as the client may read it: what happened and to which
     * status, the agent's name, nothing else.
     */
    private static function clientMeta(?array $meta): ?array
    {
        if ($meta === null) {
            return null;
        }

        return array_intersect_key($meta, array_flip(['event', 'from', 'to', 'name', 'auto']));
    }

    /** A name and a face; the id only for the team. */
    public static function person(User $user, bool $withId = true): array
    {
        return array_filter([
            'id' => $withId ? $user->id : null,
            'name' => $user->name,
            'avatar_url' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
        ], fn ($value) => $value !== null) + ['avatar_url' => null];
    }
}
