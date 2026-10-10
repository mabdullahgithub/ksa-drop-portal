<?php

namespace App\Support;

/**
 * The password the portal makes up for a new account and emails to its owner.
 *
 * Ten characters, always with an upper-case letter, a lower-case letter, a
 * digit and a special character, and never a space. Look-alikes (0/O, 1/l/I)
 * are left out because people type it in from the email, and the specials are
 * ones that survive an email unchanged.
 */
class GeneratedPassword
{
    public const LENGTH = 10;

    private const SETS = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!#$%*?+=',
    ];

    public static function make(): string
    {
        $pick = fn (string $from) => $from[random_int(0, strlen($from) - 1)];

        // One of each kind first, so none can be missing.
        $characters = array_map($pick, self::SETS);

        while (count($characters) < self::LENGTH) {
            $characters[] = $pick(implode('', self::SETS));
        }

        // Shuffled, or the first four would always be in the same order.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }
}
