<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A live chat entry for the bell: a new conversation, one assigned to you,
 * a reply, a resolved chat. The wording comes from ChatNotifier.
 *
 * Bell only. The client's email about a reply is sent separately and later
 * (SendChatReplyEmail), only when the reply is still unread.
 */
class ChatNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $message,
        private string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'icon' => 'message-square',
            'action_url' => $this->url,
        ];
    }
}
