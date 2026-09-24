<?php

namespace Tests\Unit;

use App\Services\Shipping\ArabicShaper;
use App\Services\Shipping\WaybillTextFit;
use PHPUnit\Framework\TestCase;

class ArabicShaperTest extends TestCase
{
    private function codes(string $s): string
    {
        return implode(' ', array_map(fn ($c) => sprintf('%04X', mb_ord($c)), mb_str_split($s)));
    }

    public function test_latin_text_is_untouched(): void
    {
        $this->assertSame('Riyadh, SA 12345', ArabicShaper::shape('Riyadh, SA 12345'));
    }

    public function test_letters_are_joined_and_reversed_into_visual_order(): void
    {
        // الامين: isolated alef, isolated lam-alef, initial meem, medial yeh, final noon — drawn right to left.
        $this->assertSame('FEE6 FEF4 FEE3 FEFB FE8D', $this->codes(ArabicShaper::shape('الامين')));
    }

    public function test_word_order_is_right_to_left_and_numbers_stay_left_to_right(): void
    {
        $this->assertSame(
            ArabicShaper::shape('الملك') . ' 15 ' . ArabicShaper::shape('شارع'),
            ArabicShaper::shape('شارع 15 الملك'),
        );
    }

    public function test_arabic_inside_latin_text_keeps_the_latin_first(): void
    {
        $shaped = ArabicShaper::shape('AlsahliGhalab - الامين البدراني');

        $this->assertStringStartsWith('AlsahliGhalab - ', $shaped);
        $this->assertStringEndsWith(ArabicShaper::shape('الامين'), $shaped);
    }

    public function test_html_wraps_before_shaping_so_lines_read_top_to_bottom(): void
    {
        $html = (string) WaybillTextFit::html('واحد اثنان ثلاثة اربعة', 60, 10);
        $lines = explode('<br>', $html);

        $this->assertGreaterThan(1, count($lines));
        $this->assertSame(e(ArabicShaper::shape('واحد اثنان')), $lines[0]);
    }
}
