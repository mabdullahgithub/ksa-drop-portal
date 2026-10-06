<?php

namespace App\Services\Mail;

use App\Models\EmailGraveyard;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps the real mail transport to catch the moment a mail server refuses a
 * recipient because the address does not exist, and buries that address.
 *
 * It sits at the transport because that is the only place every send passes
 * through: mail leaves from two dozen call sites, most of which catch the
 * failure and log one line, so nothing higher up ever sees it. The exception
 * is rethrown untouched — callers behave exactly as before.
 */
class RejectedRecipientTransport implements TransportInterface
{
    public function __construct(private TransportInterface $inner) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            return $this->inner->send($message, $envelope);
        } catch (TransportExceptionInterface $e) {
            try {
                $this->buryRejectedRecipient($e);
            } catch (\Throwable $recording) {
                // Losing the graveyard entry is fine; hiding the real mail
                // failure behind a database error is not.
                Log::channel('mail')->warning('Could not record rejected recipient', [
                    'error' => $recording->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    public function __toString(): string
    {
        return (string) $this->inner;
    }

    /**
     * Transport-specific methods (the array transport's messages(), SMTP's
     * stop()) keep working through the wrapper.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->inner->{$method}(...$arguments);
    }

    private function buryRejectedRecipient(TransportExceptionInterface $e): void
    {
        // A 4xx reply is "try again later" (greylisting, a busy server).
        $replyCode = (int) $e->getCode();
        if ($replyCode < 500 || $replyCode > 599) {
            return;
        }

        // The SMTP transcript's last command is the one the server refused.
        // Only an answer to RCPT TO is about a recipient: the same wording
        // after MAIL FROM is about our own sender address, and after DATA it
        // is about the message.
        if (! preg_match_all('/^(?:\[[^\]]*\] )?> (.+)$/m', $e->getDebug(), $commands)) {
            return;
        }

        if (! preg_match('/^RCPT TO:<([^>]+)>/i', trim(end($commands[1])), $rcpt)) {
            return;
        }

        $response = preg_match('/with message "(.*)"\.$/s', $e->getMessage(), $m) ? $m[1] : $e->getMessage();

        if (! AddressNotFound::matches(null, $response)) {
            return;
        }

        EmailGraveyard::bury($rcpt[1], EmailGraveyard::SOURCE_SMTP, (string) $replyCode, $response);
    }
}
