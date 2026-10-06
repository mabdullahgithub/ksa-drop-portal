<?php

namespace App\Services\Mail;

/**
 * Decides whether a mail server's answer means "this address does not exist".
 *
 * Deliberately narrow. A full mailbox, a spam block, a relay refusal or a
 * rejected sender are all permanent-looking failures too, but none of them say
 * the address is dead — and a relay refusal is returned for *every* recipient,
 * so treating it as "not found" would bury the whole user base in one bad
 * afternoon of SMTP settings.
 */
class AddressNotFound
{
    /**
     * Enhanced status codes (RFC 3463) that mean the address itself is bad:
     * unknown mailbox, unknown domain, bad syntax, mailbox moved, null MX.
     */
    private const NOT_FOUND_STATUSES = ['5.1.1', '5.1.2', '5.1.3', '5.1.6', '5.1.10'];

    /**
     * Wording mail servers use for an unknown mailbox or domain when they give
     * no telling status code (Gmail's DNS failures, Yahoo, Outlook, Exim).
     */
    private const NOT_FOUND_WORDING = '/'
        .'user unknown|unknown (user|recipient|mailbox|address)'
        .'|no such (user|mailbox|recipient|address|account|person|domain)'
        .'|(mailbox|user|recipient|account|address)[^.;]{0,60}(not found|does not exist|doesn\'?t exist|unavailable|is invalid)'
        .'|invalid (recipient|mailbox|address)'
        .'|not a valid (mailbox|user|recipient)'
        .'|doesn\'?t have a\b.{0,40}?\baccount' // Yahoo
        .'|recipient address rejected: access denied' // Office 365 for an unknown recipient
        .'|nxdomain|domain (name )?(not found|does not exist)'
        .'/i';

    /**
     * @param  string|null  $status  enhanced status code when the report carries
     *                               one separately (a bounce's "Status:" field)
     * @param  string  $diagnostic  the server's own words
     */
    public static function matches(?string $status, string $diagnostic): bool
    {
        // The diagnostic's own code is the receiving server's verdict; the
        // separate status can be a generic one the reporting server filled in
        // (Gmail reports an unknown domain as 4.0.0 or 5.0.0).
        $code = self::statusIn($diagnostic) ?? self::statusIn((string) $status);

        if ($code !== null && in_array($code, self::NOT_FOUND_STATUSES, true)) {
            return true;
        }

        // Sender problems reuse the same wording ("Sender address rejected:
        // User unknown") and say nothing about the recipient.
        if (in_array($code, ['5.1.7', '5.1.8'], true) || stripos($diagnostic, 'sender address rejected') !== false) {
            return false;
        }

        // Mailbox state (full, disabled, rate limited), message problems and
        // policy or spam blocks: the address may be perfectly fine.
        if ($code !== null && preg_match('/^[45]\.[2367]\./', $code)) {
            return false;
        }

        return preg_match(self::NOT_FOUND_WORDING, $diagnostic) === 1;
    }

    /**
     * First enhanced status code in the text, e.g. "5.1.1".
     */
    private static function statusIn(string $text): ?string
    {
        return preg_match('/(?<![\d.])([245]\.\d{1,3}\.\d{1,3})(?![\d.])/', $text, $m) ? $m[1] : null;
    }
}
