<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sending a message or an internal note, and what that does to the
 * conversation:
 *
 *  - the client writes: a chat waiting on them is open again;
 *  - the team writes: an open chat now waits on the client, and the first
 *    reply sets the first response time;
 *  - a note changes nothing but the timeline.
 *
 * Nothing is sent to a resolved or closed chat; see ChatConversation::isLocked().
 *
 * Every message keeps who sent it: the account, and its name and role as
 * they were then. A retried send (same client_uuid) is stored once and
 * returns the message already saved.
 */
class ChatMessenger
{
    public function __construct(
        private ChatWorkflow $workflow,
        private ChatBroadcaster $broadcaster,
        private ChatNotifier $notifier,
    ) {}

    /**
     * @param  array{kind?: string, body?: ?string, client_uuid?: ?string, files?: array, voice?: ?\Illuminate\Http\UploadedFile, voice_duration?: ?int}  $input
     */
    public function send(ChatConversation $conversation, User $by, string $senderType, array $input, bool $opening = false): ChatMessage
    {
        $kind = $input['kind'] ?? ChatMessage::MESSAGE;
        $body = isset($input['body']) ? trim((string) $input['body']) : null;
        $body = $body === '' ? null : $body;

        if ($kind === ChatMessage::NOTE && $senderType !== ChatMessage::FROM_STAFF) {
            abort(403);
        }

        if ($conversation->isLocked()) {
            abort(422, $senderType === ChatMessage::FROM_CLIENT
                ? "This chat is {$conversation->status}. Start a new chat and we will pick it up."
                : "This chat is {$conversation->status}. Reopen it to reply.");
        }

        // Checked before any file is written.
        $items = ChatSharedItems::snapshot(
            $conversation->client,
            $input['orders'] ?? [],
            $input['products'] ?? [],
            byTeam: $senderType === ChatMessage::FROM_STAFF,
        );

        $rows = ChatAttachmentStore::put($conversation, $input['files'] ?? []);
        if (! empty($input['voice'])) {
            $rows[] = ChatAttachmentStore::putVoice($conversation, $input['voice'], (int) $input['voice_duration']);
        }

        try {
            [$message, $after] = DB::transaction(function () use ($conversation, $by, $senderType, $kind, $body, $items, $input, $rows) {
                $held = ChatConversation::acrossClients()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                $held->setRelation('client', $conversation->client);

                // Checked again on the locked row: it may have been resolved meanwhile.
                if ($held->isLocked()) {
                    abort(422, "This chat is {$held->status}.");
                }

                if (! empty($input['client_uuid'])) {
                    $existing = $held->messages()->where('client_uuid', $input['client_uuid'])->first();
                    if ($existing) {
                        ChatAttachmentStore::forget($rows);

                        return [$existing, null];
                    }
                }

                $message = $held->messages()->create(ChatMessage::senderSnapshot($by, $senderType) + [
                    'sender_type' => $senderType,
                    'kind' => $kind,
                    'internal' => $kind === ChatMessage::NOTE,
                    'body' => $body,
                    'shared_items' => $items,
                    'origin' => $input['origin'] ?? null,
                    'client_uuid' => $input['client_uuid'] ?? null,
                ]);
                ChatAttachmentStore::attach($message, $rows);

                $after = $kind === ChatMessage::MESSAGE
                    ? $this->advance($held, $by, $senderType)
                    : ['events' => [], 'changed' => false];

                $held->save();
                ChatUnread::markRead($held, $by->id, $message->id);

                $message->setRelation('conversation', $held);
                $message->setRelation('sender', $by);
                $message->load('attachments');

                $this->broadcaster->messageCreated($message);
                foreach ($after['events'] as $event) {
                    $this->broadcaster->messageCreated($event);
                }
                if ($after['changed']) {
                    $this->broadcaster->conversationUpdated($held);
                }

                return [$message, $after];
            });
        } catch (\Throwable $e) {
            ChatAttachmentStore::forget($rows);

            throw $e;
        }

        if ($after !== null && ! $opening) {
            $this->tell($message, $senderType, $after);
        } elseif ($after !== null && $opening && $senderType === ChatMessage::FROM_STAFF) {
            $this->notifier->teamReplied($message->conversation, $message, firstSinceClient: false, opening: true);
        }

        return $message;
    }

    /**
     * Move the conversation on for a message, on the locked row.
     *
     * @return array{events: list<ChatMessage>, changed: bool, returned?: bool, first_since_client?: bool}
     */
    private function advance(ChatConversation $held, User $by, string $senderType): array
    {
        $now = now();
        $events = [];
        $changed = false;
        $held->last_message_at = $now;

        if ($senderType === ChatMessage::FROM_CLIENT) {
            $returned = false;
            $held->last_client_message_at = $now;

            if ($held->status === ChatConversation::PENDING) {
                $this->workflow->applyStatus($held, ChatConversation::OPEN, $by);
                $changed = $returned = true;
            }

            return ['events' => $events, 'changed' => $changed, 'returned' => $returned];
        }

        // The team wrote.
        $firstSinceClient = $held->last_client_message_at !== null
            && ($held->last_staff_message_at === null || $held->last_staff_message_at->lt($held->last_client_message_at));

        $held->last_staff_message_at = $now;

        if ($held->opened_by === ChatConversation::BY_CLIENT && $held->first_response_at === null) {
            $held->first_response_at = $now;
        }

        if ($held->status === ChatConversation::OPEN) {
            $held->status = ChatConversation::PENDING;
            $changed = true;
        }

        return ['events' => $events, 'changed' => $changed, 'first_since_client' => $firstSinceClient];
    }

    private function tell(ChatMessage $message, string $senderType, array $after): void
    {
        $conversation = $message->conversation;

        if ($senderType === ChatMessage::FROM_CLIENT) {
            if ($after['returned'] ?? false) {
                $this->notifier->clientReturned($conversation, $message->sender);
            }

            return;
        }

        $this->notifier->teamReplied($conversation, $message, $after['first_since_client'] ?? false);
    }
}
