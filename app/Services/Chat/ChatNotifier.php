<?php

namespace App\Services\Chat;

use App\Jobs\SendChatReplyEmail;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\ChatNotification;
use Illuminate\Support\Facades\DB;

/**
 * Who hears about what in live chat, on the bell (and, for a client, by
 * email when a reply sits unread).
 *
 * Messages themselves already show up live in the inbox and on the sidebar
 * number, so the bell only rings when a conversation needs someone:
 *  - a client opens one: every agent who may see it;
 *  - a client writes again on one that was waiting on them or resolved:
 *    every agent who may see it;
 *  - the team replies, starts a chat, or resolves one: the client.
 *
 * A notification that fails never undoes what it was about, and nothing is
 * sent until the change is committed.
 */
class ChatNotifier
{
    public function started(ChatConversation $conversation, User $by): void
    {
        $this->later(function () use ($conversation, $by) {
            if ($conversation->opened_by === ChatConversation::BY_CLIENT) {
                $this->team($conversation, $by, 'New support chat', "{$conversation->client?->company_name}: {$conversation->subject}");
            }
        });
    }

    /** A client wrote on a conversation that had gone quiet on our side. */
    public function clientReturned(ChatConversation $conversation, User $by): void
    {
        $this->later(fn () => $this->team(
            $conversation,
            $by,
            'Client replied',
            "{$conversation->client?->company_name} replied on \"{$conversation->subject}\".",
        ));
    }

    /**
     * The team wrote to the client. The bell rings on the first reply after
     * the client last wrote; the email waits and checks it is still unread.
     */
    public function teamReplied(ChatConversation $conversation, ChatMessage $message, bool $firstSinceClient, bool $opening = false): void
    {
        $this->later(function () use ($conversation, $message, $firstSinceClient, $opening) {
            if ($firstSinceClient || $opening) {
                $conversation->client?->user?->notify(new ChatNotification(
                    $opening ? 'New message from support' : 'Support replied',
                    $opening
                        ? "{$message->sender?->name} started a chat with you: \"{$conversation->subject}\"."
                        : "{$message->sender?->name} replied on \"{$conversation->subject}\".",
                    $this->clientUrl($conversation),
                ));
            }

            SendChatReplyEmail::dispatch($message->id)->delay(now()->addMinutes(config('chat.reply_mail_delay_minutes')));
        });
    }

    public function resolved(ChatConversation $conversation, User $by): void
    {
        $this->later(fn () => $conversation->client?->user?->notify(new ChatNotification(
            'Chat resolved',
            "\"{$conversation->subject}\" was marked resolved. Reply any time to reopen it.",
            $this->clientUrl($conversation),
        )));
    }

    private function team(ChatConversation $conversation, User $by, string $title, string $message): void
    {
        ChatVisibility::audience($conversation)
            ->reject(fn (User $user) => $user->id === $by->id)
            ->each(fn (User $user) => $user->notify(new ChatNotification($title, $message, $this->teamUrl($conversation))));
    }

    private function teamUrl(ChatConversation $conversation): string
    {
        return '/chats?chat=' . $conversation->id;
    }

    private function clientUrl(ChatConversation $conversation): string
    {
        return '/portal/support?chat=' . $conversation->id;
    }

    private function later(callable $send): void
    {
        DB::afterCommit(function () use ($send) {
            try {
                $send();
            } catch (\Throwable) {
            }
        });
    }
}
