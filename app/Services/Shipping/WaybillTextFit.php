<?php

namespace App\Services\Shipping;

/**
 * Sizes waybill text so it always shows in full *and* the label stays on one
 * page: every field has a fixed box, and instead of truncating we pick the
 * largest font size at which the whole string wraps into that box.
 *
 * dompdf can't measure text for us before layout, so this estimates with
 * DejaVu Sans' average glyph widths (em fractions, deliberately generous so
 * the estimate errs toward a smaller font rather than overflowing).
 */
class WaybillTextFit
{
    public const REGULAR = 0.6;

    public const BOLD = 0.68;

    public const BOLD_UPPER = 0.8;

    /** Line box height as a multiple of font size (CSS line-height 1.3 plus dompdf's glyph extents). */
    public const LINE = 1.45;

    /**
     * Largest size in $sizes (px, descending) at which $text fits a box of
     * $width x $height px. Falls back to the smallest size.
     */
    public static function size(string $text, float $width, float $height, array $sizes, float $em = self::REGULAR): float
    {
        foreach ($sizes as $size) {
            if (self::fits($text, $width, $height, $size, $em)) {
                return $size;
            }
        }

        return end($sizes);
    }

    public static function fits(string $text, float $width, float $height, float $size, float $em = self::REGULAR): bool
    {
        return self::lines($text, $width, $size, $em) * $size * self::LINE <= $height;
    }

    /**
     * Fit a list (e.g. the order's items) into a box. Shrinks the font first;
     * if even the smallest size can't hold every entry, drops whole trailing
     * entries and appends "+N more" — never cutting an entry mid-text.
     *
     * @return array{0: string, 1: float} [text, size]
     */
    public static function list(array $entries, string $separator, float $width, float $height, array $sizes, string $moreSuffix, float $em = self::REGULAR): array
    {
        $all = implode($separator, $entries);

        foreach ($sizes as $size) {
            if (self::fits($all, $width, $height, $size, $em)) {
                return [$all, $size];
            }
        }

        $min = end($sizes);

        for ($keep = count($entries) - 1; $keep >= 0; $keep--) {
            $more = count($entries) - $keep;
            $text = trim(implode($separator, array_slice($entries, 0, $keep)) . $separator . sprintf($moreSuffix, $more), $separator . ' ');

            if (self::fits($text, $width, $height, $min, $em)) {
                return [$text, $min];
            }
        }

        return [sprintf($moreSuffix, count($entries)), $min];
    }

    /** Number of lines $text wraps to, breaking at spaces like the renderer does. */
    public static function lines(string $text, float $width, float $size, float $em = self::REGULAR): int
    {
        $perLine = max(1, (int) floor($width / ($size * $em)));
        $lines = 0;

        foreach (preg_split('/\R/u', $text) as $paragraph) {
            $lines++;
            $used = 0;

            foreach (preg_split('/\s+/u', trim($paragraph)) as $word) {
                $len = mb_strlen($word);

                // A word longer than a line is broken across lines.
                if ($len > $perLine) {
                    if ($used > 0) {
                        $lines++;
                    }
                    $lines += intdiv($len - 1, $perLine);
                    $used = $len % $perLine ?: $perLine;
                    continue;
                }

                $needed = $used === 0 ? $len : $used + 1 + $len;

                if ($needed > $perLine) {
                    $lines++;
                    $used = $len;
                } else {
                    $used = $needed;
                }
            }
        }

        return $lines;
    }
}
