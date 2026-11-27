<?php

namespace App\Jobs;

use App\Mail\Clients\ChatReplyMail;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email the client about a reply, a few minutes after it was sent, and only
 * if they still have not read it. A burst of replies sends one email: each
 * job steps aside for a newer reply, whose own job sends.
 *
 * Always on the `database` connection: the VPS's default is `sync`, which
 * ignores the delay and would email at once, while the agent waits. Its own
 * `chat` queue, so a slow mail server never holds up the Shopify jobs.
 */
class SendChatReplyEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $messageId)
    {
        $this->onConnection('database')->onQueue('chat');
    }

    public function handle(EmailService $email): void
    {
        $reply = ChatMessage::with(['conversation.client.user', 'sender'])->find($this->messageId);
        $conversation = $reply?->conversation;
        $client = $conversation?->client;
        $recipient = $client?->user;

        if (! $reply || ! $conversation || ! $recipient?->email || $reply->internal || ! $email->isEnabled()) {
            return;
        }

        $read = (int) ChatRead::where('conversation_id', $conversation->id)->where('user_id', $recipient->id)->value('last_read_message_id');
        if ($read >= $reply->id) {
            return;
        }

        $newer = $conversation->messages()
            ->where('id', '>', $reply->id)
            ->where('kind', ChatMessage::MESSAGE)
            ->where('sender_type', ChatMessage::FROM_STAFF)
            ->where('internal', false)
            ->exists();
        if ($newer) {
            return;
        }

        try {
            $email->configureMailer();
            Mail::to($recipient->email)->send(new ChatReplyMail($conversation, $reply));
        } catch (\Throwable $e) {
            Log::warning('chat.reply_mail_failed', ['conversation_id' => $conversation->id, 'message_id' => $reply->id, 'error' => $e::class]);
        }
    }
}
