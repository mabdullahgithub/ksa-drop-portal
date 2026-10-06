<?php

namespace App\Services\Mail;

use App\Models\EmailGraveyard;

/**
 * Reads a bounce email (a delivery status notification, RFC 3464) and proves
 * it is about a message this application really sent.
 *
 * The proof matters. The mailbox we read bounces from accepts mail from
 * anyone, so without it a stranger could email a hand-written "address not
 * found" report naming any user and silence that user's password resets and
 * security alerts. Every outgoing message therefore carries a token per
 * recipient, signed with the app key; a real bounce quotes the original
 * message's headers back, token included, and a forged one cannot.
 */
class BounceReport
{
    /** Header on every outgoing message, quoted back inside a real bounce. */
    public const TOKEN_HEADER = 'X-Bounce-Token';

    /**
     * The token that proves we sent mail to this address.
     */
    public static function tokenFor(string $email): string
    {
        return substr(hash_hmac('sha256', 'mail-bounce|'.EmailGraveyard::normalize($email), (string) config('app.key')), 0, 32);
    }

    /**
     * Whether the raw message is a delivery status notification at all.
     */
    public static function isDeliveryReport(string $raw): bool
    {
        $headers = self::unfold(preg_split('/\r?\n\r?\n/', $raw, 2)[0]);

        return preg_match('/^Content-Type:\s*multipart\/report\b[^\n]*report-type="?delivery-status/im', $headers) === 1;
    }

    /**
     * The recipients the report says could not be delivered to.
     *
     * @return array<int, array{recipients: array<int, string>, status: string|null, diagnostic: string}>
     */
    public static function failures(string $raw): array
    {
        $start = stripos($raw, 'message/delivery-status');

        if ($start === false) {
            return [];
        }

        $section = substr($raw, $start);

        // Stop at the quoted original message so its own headers and body are
        // never mistaken for report fields.
        if (preg_match('/^Content-Type:\s*(message\/rfc822|text\/rfc822-headers)/im', $section, $m, PREG_OFFSET_CAPTURE)) {
            $section = substr($section, 0, $m[0][1]);
        }

        $failures = [];

        // One blank-line-separated block per recipient.
        foreach (preg_split('/\n[ \t]*\n/', self::unfold($section)) as $block) {
            if (! preg_match('/^Final-Recipient:\s*(.+)$/im', $block, $final)) {
                continue;
            }

            // Only a final verdict counts: "delayed" means the server is
            // still trying, "relayed" and "delivered" are not failures.
            if (! preg_match('/^Action:\s*failed\b/im', $block)) {
                continue;
            }

            // The address we wrote to comes first: when mail is forwarded,
            // Final-Recipient is wherever it ended up, not who we sent it to.
            $recipients = [];
            if (preg_match('/^Original-Recipient:\s*(.+)$/im', $block, $original)) {
                $recipients[] = self::address($original[1]);
            }
            $recipients[] = self::address($final[1]);

            $failures[] = [
                'recipients' => array_values(array_unique(array_filter($recipients))),
                'status' => preg_match('/^Status:\s*(\d\.\d{1,3}\.\d{1,3})/im', $block, $status) ? $status[1] : null,
                'diagnostic' => preg_match('/^Diagnostic-Code:\s*(.+)$/im', $block, $diagnostic) ? trim($diagnostic[1]) : '',
            ];
        }

        return $failures;
    }

    /**
     * The first of the given addresses we can prove we sent this message to,
     * or null when the report quotes no valid token for any of them.
     *
     * @param  array<int, string>  $recipients
     */
    public static function verifiedRecipient(string $raw, array $recipients): ?string
    {
        preg_match_all('/^'.preg_quote(self::TOKEN_HEADER, '/').':\s*(.+)$/im', self::unfold($raw), $matches);

        $quoted = [];
        foreach ($matches[1] as $value) {
            array_push($quoted, ...preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY));
        }

        foreach ($recipients as $recipient) {
            $expected = self::tokenFor($recipient);

            foreach ($quoted as $token) {
                if (hash_equals($expected, $token)) {
                    return EmailGraveyard::normalize($recipient);
                }
            }
        }

        return null;
    }

    /**
     * Join folded header lines (a line starting with whitespace continues the
     * one above) and normalise line endings.
     */
    private static function unfold(string $text): string
    {
        return preg_replace('/\n[ \t]+(?=\S)/', ' ', str_replace("\r\n", "\n", $text));
    }

    /**
     * "rfc822; <someone@example.com>" → "someone@example.com".
     */
    private static function address(string $field): ?string
    {
        $value = trim(str_contains($field, ';') ? substr($field, strpos($field, ';') + 1) : $field);
        $value = EmailGraveyard::normalize(trim($value, "<> \t"));

        return str_contains($value, '@') ? $value : null;
    }
}
