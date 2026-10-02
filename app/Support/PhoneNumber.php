<?php

namespace App\Support;

/**
 * Phone numbers in one canonical international form, for two audiences.
 *
 * Riders sign in with their phone number, so every way of typing the same
 * number has to land on the same row. normalize() accepts Saudi and Pakistani
 * mobiles only:
 *
 *   Saudi     05XXXXXXXX  / 5XXXXXXXX  / 9665… / +966… / 00966…  → +9665XXXXXXXX
 *   Pakistan  03XXXXXXXXX / 3XXXXXXXXX / 923…  / +92…  / 0092…   → +923XXXXXXXXX
 *
 * The local forms can't be confused: a Saudi mobile starts 05, a Pakistani
 * one 03. Spaces, dashes and Arabic-Indic digits are ignored.
 *
 * Customers: `customer_phone` / `shipping_phone` are free-text across all
 * three order sources (CSV import, manual entry, Shopify sync), so the same
 * Saudi mobile arrives as any of `0501234567`, `+966 50 123 4567`,
 * `00966501234567`, `966-50-123-4567`, or with Arabic-Indic digits pasted from
 * a spreadsheet. WhatsApp needs one canonical `+966501234567`, both to address
 * the outbound message and to match the inbound reply back to an order —
 * that is toE164().
 *
 * Deliberately no libphonenumber dependency — the same lightweight
 * custom-logic approach as {@see \App\Models\Order::isCashOnDelivery()}. We
 * only need KSA-shaped numbers to be right, and to reject anything we can't
 * confidently normalise rather than guess and message a stranger.
 */
class PhoneNumber
{
    public const HINT = 'Enter a Saudi (05XXXXXXXX) or Pakistani (03XXXXXXXXX) mobile number.';

    /**
     * Country calling code → expected national significant number length.
     *
     * GCC codes are the delivery markets. Pakistan is here because staff and
     * WhatsApp test recipients use +92 numbers — without it, Meta's registered
     * test recipient normalises to null and the send job bails before it ever
     * reaches the API.
     */
    private const NSN_LENGTH = [
        '966' => 9,  // Saudi Arabia
        '971' => 9,  // UAE
        '973' => 8,  // Bahrain
        '965' => 8,  // Kuwait
        '968' => 8,  // Oman
        '974' => 8,  // Qatar
        '92' => 10,  // Pakistan — staff / WhatsApp test recipients
    ];

    /**
     * A rider's number in canonical form, or null when it isn't a supported
     * mobile number.
     */
    public static function normalize(?string $input): ?string
    {
        $digits = self::digitsOnly((string) $input);

        // International prefix typed as 00.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return match (true) {
            (bool) preg_match('/^9665\d{8}$/', $digits) => '+' . $digits,
            (bool) preg_match('/^05\d{8}$/', $digits) => '+966' . substr($digits, 1),
            (bool) preg_match('/^5\d{8}$/', $digits) => '+966' . $digits,
            (bool) preg_match('/^923\d{9}$/', $digits) => '+' . $digits,
            (bool) preg_match('/^03\d{9}$/', $digits) => '+92' . substr($digits, 1),
            (bool) preg_match('/^3\d{9}$/', $digits) => '+92' . $digits,
            default => null,
        };
    }

    /**
     * How the rider types it at home: 05XXXXXXXX or 03XXXXXXXXX.
     */
    public static function local(string $normalized): string
    {
        return match (true) {
            str_starts_with($normalized, '+966') => '0' . substr($normalized, 4),
            str_starts_with($normalized, '+92') => '0' . substr($normalized, 3),
            default => $normalized,
        };
    }

    /**
     * Digits only, as wa.me links want them: 9665XXXXXXXX / 923XXXXXXXXX.
     */
    public static function forWhatsApp(string $normalized): string
    {
        return ltrim($normalized, '+');
    }

    /**
     * @return string|null E.164 (`+966501234567`), or null when the input
     *                     cannot be normalised with confidence.
     */
    public static function toE164(?string $raw, string $defaultCallingCode = '966'): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = self::digitsOnly($raw);

        if ($digits === '') {
            return null;
        }

        // 00966… international prefix → drop the trunk zeros.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Already carries a known country code.
        foreach (self::NSN_LENGTH as $code => $length) {
            if (str_starts_with($digits, $code) && strlen($digits) === strlen($code) + $length) {
                return '+' . $digits;
            }
        }

        $expected = self::NSN_LENGTH[$defaultCallingCode] ?? null;

        if ($expected === null) {
            return null;
        }

        // National format with a leading trunk zero: 0501234567.
        if (str_starts_with($digits, '0') && strlen($digits) === $expected + 1) {
            return '+' . $defaultCallingCode . substr($digits, 1);
        }

        // Bare national significant number: 501234567.
        if (strlen($digits) === $expected) {
            return '+' . $defaultCallingCode . $digits;
        }

        // Anything else (too short, too long, unknown country) is not something
        // we should guess at — messaging the wrong person is worse than not
        // messaging at all.
        return null;
    }

    /**
     * Strip formatting and fold Arabic-Indic / Eastern Arabic-Indic digits to
     * ASCII. Spreadsheet exports and Arabic-locale Shopify stores both produce
     * these, and they would otherwise be silently discarded as non-digits.
     */
    private static function digitsOnly(string $value): string
    {
        $arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $easternArabicIndic = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ascii = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($arabicIndic, $ascii, $value);
        $value = str_replace($easternArabicIndic, $ascii, $value);

        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
