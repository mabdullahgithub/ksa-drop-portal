<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;

/**
 * Mirrors every outbound message into the dedicated "mail" log channel so a
 * failed send can be traced without digging through the shared app log.
 */
class LogMailActivity
{
    /**
     * Log the message on its way to the transport.
     */
    public function sending(MessageSending $event): void
    {
        Log::channel('mail')->info('Mail sending', $this->context($event->message) + [
            'mailer' => $event->data['__laravel_mailable'] ?? null,
            'transport' => config('mail.default'),
            'host' => config('mail.mailers.'.config('mail.default').'.host'),
            'port' => config('mail.mailers.'.config('mail.default').'.port'),
        ]);
    }

    /**
     * Log the successful handoff to the transport.
     */
    public function sent(MessageSent $event): void
    {
        Log::channel('mail')->info('Mail sent', $this->context($event->message) + [
            'message_id' => $event->sent->getMessageId(),
        ]);
    }

    /**
     * Envelope details, without the message body.
     */
    protected function context(Email $message): array
    {
        return [
            'subject' => $message->getSubject(),
            'from' => $this->addresses($message->getFrom()),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
        ];
    }

    /**
     * @param  array<int, \Symfony\Component\Mime\Address>  $addresses
     * @return array<int, string>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(fn ($address) => $address->getAddress(), $addresses);
    }
}
