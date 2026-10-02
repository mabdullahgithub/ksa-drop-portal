<?php

namespace App\Support;

/**
 * Rider mobile numbers in one canonical international form.
 *
 * Riders sign in with their phone number, so every way of typing the same
 * number has to land on the same row. Saudi and Pakistani mobiles are
 * accepted:
 *
 *   Saudi     05XXXXXXXX  / 5XXXXXXXX  / 9665… / +966… / 00966…  → +9665XXXXXXXX
 *   Pakistan  03XXXXXXXXX / 3XXXXXXXXX / 923…  / +92…  / 0092…   → +923XXXXXXXXX
 *
 * The local forms can't be confused: a Saudi mobile starts 05, a Pakistani
 * one 03. Spaces, dashes and Arabic-Indic digits are ignored.
 */
class PhoneNumber
{
    public const HINT = 'Enter a Saudi (05XXXXXXXX) or Pakistani (03XXXXXXXXX) mobile number.';

    /**
     * The canonical form, or null when it isn't a supported mobile number.
     */
    public static function normalize(?string $input): ?string
    {
        $raw = trim((string) $input);
        $digits = strtr($raw, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $digits = preg_replace('/\D+/', '', $digits);

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
}
