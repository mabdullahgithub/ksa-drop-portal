<?php

namespace App\Listeners;

use App\Logging\AppLog;
use App\Logging\Events\MailEvent;
use App\Logging\ExternalId;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

/**
 * Records every outbound message as coded events: which mailable and how many
 * recipients. Never addresses or subjects; those stay out of the logs.
 */
class LogMailActivity
{
    /**
     * Log the message on its way to the transport.
     */
    public function sending(MessageSending $event): void
    {
        AppLog::emit(MailEvent::MESSAGE_SENDING, cnt: $this->recipients($event->message), mtp: $this->mailable($event->data));
    }

    /**
     * Log the successful handoff to the transport.
     */
    public function sent(MessageSent $event): void
    {
        AppLog::emit(MailEvent::MESSAGE_SENT, cnt: $this->recipients($event->message), mtp: $this->mailable($event->data));
    }

    private function recipients(Email $message): int
    {
        return count($message->getTo()) + count($message->getCc()) + count($message->getBcc());
    }

    /** @param array<string, mixed> $data */
    private function mailable(array $data): ?string
    {
        $class = $data['__laravel_mailable'] ?? null;

        return is_string($class) ? ExternalId::orNull(class_basename($class)) : null;
    }
}
