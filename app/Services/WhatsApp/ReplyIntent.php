<?php

namespace App\Services\WhatsApp;

/**
 * Classifies a customer's WhatsApp reply into an action.
 *
 * The templates ask for a digit (1 confirm / 2 update address / 3 cancel)
 * because that is what we can interpret reliably, but real customers reply
 * "ايوه", "تمام", "yes ok", or paste a whole new address instead. So: match the
 * digit first, then a keyword list in both languages, and otherwise return
 * UNKNOWN — which routes the order to an agent rather than guessing.
 *
 * Nothing here auto-edits an address. A reply classified as UPDATE_ADDRESS
 * flags the order for an agent, who applies the change through the existing
 * order UI; parsing free-text Saudi addresses reliably is its own project.
 */
class ReplyIntent
{
    public const CONFIRM = 'confirm';
    public const UPDATE_ADDRESS = 'update_address';
    public const CANCEL = 'cancel';
    public const UNKNOWN = 'unknown';

    private const KEYWORDS = [
        self::CONFIRM => [
            'confirm', 'confirmed', 'yes', 'yep', 'yeah', 'ok', 'okay', 'sure', 'correct', 'right', 'good',
            'نعم', 'ايوه', 'أيوه', 'اي', 'تمام', 'موافق', 'اكيد', 'أكيد', 'صح', 'تأكيد', 'تاكيد', 'ماشي', 'اوك',
        ],
        self::CANCEL => [
            'cancel', 'cancelled', 'no', 'nope', 'stop', 'refuse', 'dont want', "don't want", 'not interested', 'remove',
            'الغاء', 'إلغاء', 'الغي', 'ألغي', 'لا', 'مش عايز', 'ما ابغى', 'لا اريد', 'مو ابي', 'ما ابي', 'رفض',
        ],
        self::UPDATE_ADDRESS => [
            'address', 'update', 'change', 'wrong address', 'new address', 'different',
            'عنوان', 'العنوان', 'تغيير', 'تعديل', 'عنواني', 'غلط', 'خطأ',
        ],
    ];

    public static function classify(?string $body): string
    {
        $text = mb_strtolower(trim((string) $body));

        if ($text === '') {
            return self::UNKNOWN;
        }

        // A bare digit is the templated happy path — check it before keywords
        // so "1" never matches something incidental.
        $digit = preg_replace('/[^0-9]/', '', $text);

        if ($digit !== '' && mb_strlen($text) <= 3) {
            return match ($digit) {
                '1' => self::CONFIRM,
                '2' => self::UPDATE_ADDRESS,
                '3' => self::CANCEL,
                default => self::UNKNOWN,
            };
        }

        // Cancel is checked before confirm: "no, cancel it" contains neither an
        // ambiguous token nor a confirm word, but "not ok" would otherwise trip
        // the confirm list on "ok".
        foreach ([self::CANCEL, self::UPDATE_ADDRESS, self::CONFIRM] as $intent) {
            foreach (self::KEYWORDS[$intent] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $intent;
                }
            }
        }

        // A long reply that matched nothing is very often a pasted address.
        return mb_strlen($text) > 25 ? self::UPDATE_ADDRESS : self::UNKNOWN;
    }
}
