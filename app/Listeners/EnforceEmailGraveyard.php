<?php

namespace App\Listeners;

use App\Models\EmailGraveyard;
use App\Services\Mail\BounceReport;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;

/**
 * Keeps mail away from addresses in the graveyard.
 *
 * Runs on every outgoing message, whichever of the mailables, notifications
 * or raw sends produced it, so no call site has to remember to check.
 */
class EnforceEmailGraveyard
{
    /**
     * Returning false cancels the send; anything else lets it through.
     *
     * Not named handle() on purpose: the framework auto-registers listeners
     * with that name, and this one is registered by hand in AppServiceProvider
     * so that it is certain to run before the mail logger.
     */
    public function sending(MessageSending $event): ?bool
    {
        $message = $event->message;
        $hadRecipients = $this->recipients($message) !== [];

        try {
            $this->removeBuriedRecipients($message);
        } catch (\Throwable $e) {
            // The graveyard must never be the reason mail stops going out —
            // a missing table after a deploy, say. Send as if it were empty.
            Log::channel('mail')->warning('Graveyard check failed — sending anyway', [
                'error' => $e->getMessage(),
            ]);
        }

        // Everyone this message was for is buried: there is nothing to send.
        if ($hadRecipients && $this->recipients($message) === []) {
            return false;
        }

        // A bounce quotes these back, which is how we know it is genuine.
        $headers = $message->getHeaders();
        $headers->remove(BounceReport::TOKEN_HEADER);
        $headers->addTextHeader(
            BounceReport::TOKEN_HEADER,
            implode(', ', array_map(BounceReport::tokenFor(...), $this->recipients($message)))
        );

        return null;
    }

    /**
     * Strip buried addresses from To, Cc and Bcc, so a message to several
     * people still reaches the ones whose addresses work.
     */
    protected function removeBuriedRecipients(Email $message): void
    {
        $buried = EmailGraveyard::buriedAmong($this->recipients($message));

        if ($buried === []) {
            return;
        }

        foreach (['To' => 'to', 'Cc' => 'cc', 'Bcc' => 'bcc'] as $header => $setter) {
            $kept = array_values(array_filter(
                $message->{'get'.$header}(),
                fn ($address) => ! in_array(EmailGraveyard::normalize($address->getAddress()), $buried, true)
            ));

            if ($kept === []) {
                $message->getHeaders()->remove($header);
            } else {
                $message->{$setter}(...$kept);
            }
        }

        EmailGraveyard::whereIn('email', $buried)->increment('blocked_count', 1, ['last_blocked_at' => now()]);

        Log::channel('mail')->info('Mail not sent to graveyard address', [
            'subject' => $message->getSubject(),
            'blocked' => $buried,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function recipients(Email $message): array
    {
        return array_map(
            fn ($address) => $address->getAddress(),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()]
        );
    }
}
