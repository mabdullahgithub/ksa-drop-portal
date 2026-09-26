<?php

namespace App\Services\Shipping;

/**
 * Makes Arabic printable with dompdf, which draws each code point on its own
 * and left to right: letters come out unjoined and words spelled backwards.
 *
 * shape() does what a text engine would, for one line of text:
 *  1. joining — swaps each letter for its isolated/initial/medial/final
 *     presentation form (Unicode FE70–FEFF / FB50–FDFF, which DejaVu Sans
 *     has), including the lam-alef ligatures;
 *  2. bidi — reorders the line into visual order (a simplified Unicode bidi
 *     algorithm: Arabic runs reversed, Latin words and numbers kept LTR).
 *
 * Works per line: text that wraps must be split into lines first (see
 * WaybillTextFit::wrap()), otherwise the renderer would break a reversed
 * line and put its end on the first row.
 */
class ArabicShaper
{
    /** Letter => [isolated, final, initial, medial]; two entries = joins only to the previous letter. */
    private const FORMS = [
        0x0621 => [0xFE80],
        0x0622 => [0xFE81, 0xFE82],
        0x0623 => [0xFE83, 0xFE84],
        0x0624 => [0xFE85, 0xFE86],
        0x0625 => [0xFE87, 0xFE88],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA],
        0x0630 => [0xFEAB, 0xFEAC],
        0x0631 => [0xFEAD, 0xFEAE],
        0x0632 => [0xFEAF, 0xFEB0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640], // tatweel
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE],
        0x0649 => [0xFEEF, 0xFEF0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        // Persian letters common in Gulf names/brands.
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], // پ
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D], // چ
        0x0698 => [0xFB8A, 0xFB8B],                 // ژ
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], // ک
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95], // گ
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF], // ی
    ];

    /** Alef after lam => [isolated, final] lam-alef ligature. */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    private const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<', '«' => '»', '»' => '«'];

    public static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /** One line of logical-order text => visual-order text with joined letters. */
    public static function shape(string $line): string
    {
        if (! self::hasArabic($line)) {
            return $line;
        }

        // Harakat and other combining marks: dropped. They'd need glyph
        // positioning dompdf doesn't do, and shipping text rarely has them.
        $line = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $line);

        return self::reorder(self::join(mb_str_split($line)));
    }

    /** @param  string[]  $chars */
    private static function join(array $chars): array
    {
        $cps = array_map('mb_ord', $chars);
        $out = [];
        $n = count($cps);

        for ($i = 0; $i < $n; $i++) {
            $cp = $cps[$i];

            if (! isset(self::FORMS[$cp])) {
                $out[] = $chars[$i];
                continue;
            }

            $joinsPrev = $i > 0 && self::joinsNext($cps[$i - 1]);

            if ($cp === 0x0644 && isset($cps[$i + 1], self::LAM_ALEF[$cps[$i + 1]])) {
                $out[] = mb_chr(self::LAM_ALEF[$cps[$i + 1]][$joinsPrev ? 1 : 0]);
                $i++;
                continue;
            }

            $forms = self::FORMS[$cp];
            $joinsNext = count($forms) === 4 && isset($cps[$i + 1], self::FORMS[$cps[$i + 1]]) && $cps[$i + 1] !== 0x0621;

            $form = match (true) {
                count($forms) === 1 => 0,
                $joinsPrev && $joinsNext => 3,
                $joinsNext => 2,
                $joinsPrev => 1,
                default => 0,
            };

            $out[] = mb_chr($forms[$form]);
        }

        return $out;
    }

    /** Whether a letter connects to the one after it (dual-joining). */
    private static function joinsNext(int $cp): bool
    {
        return isset(self::FORMS[$cp]) && count(self::FORMS[$cp]) === 4;
    }

    /**
     * Visual order for one line. Paragraph direction comes from the first
     * strong character; neutrals (spaces, punctuation) between two runs of
     * the same direction take it, otherwise the paragraph's.
     *
     * @param  string[]  $chars
     */
    private static function reorder(array $chars): string
    {
        $types = array_map(fn ($c) => self::type($c), $chars);
        $strong = array_values(array_filter($types, fn ($t) => $t !== 'N'));
        $base = ($strong[0] ?? 'L');
        $n = count($types);

        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] !== 'N') {
                continue;
            }
            $j = $i;
            while ($j < $n && $types[$j] === 'N') {
                $j++;
            }
            $before = $i > 0 ? $types[$i - 1] : $base;
            $after = $j < $n ? $types[$j] : $base;
            $resolved = $before === $after ? $before : $base;
            for ($k = $i; $k < $j; $k++) {
                $types[$k] = $resolved;
            }
            $i = $j - 1;
        }

        // Group into runs of one direction.
        $runs = [];
        foreach ($chars as $i => $c) {
            if ($runs && end($runs)['type'] === $types[$i]) {
                $runs[count($runs) - 1]['chars'][] = $c;
            } else {
                $runs[] = ['type' => $types[$i], 'chars' => [$c]];
            }
        }

        $runs = array_map(function ($run) {
            if ($run['type'] === 'R') {
                $run['chars'] = array_map(fn ($c) => self::MIRROR[$c] ?? $c, array_reverse($run['chars']));
            }

            return implode('', $run['chars']);
        }, $runs);

        return implode('', $base === 'R' ? array_reverse($runs) : $runs);
    }

    /** R = Arabic letter, L = other letter or any digit, N = neutral. */
    private static function type(string $c): string
    {
        if (preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $c)) {
            // Arabic digits read left to right; the Arabic comma is punctuation.
            return preg_match('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $c) ? 'L' : ($c === '،' ? 'N' : 'R');
        }

        return preg_match('/[\p{L}\p{N}]/u', $c) ? 'L' : 'N';
    }
}
