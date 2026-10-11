<?php

declare(strict_types=1);

namespace QrStudio\Tests;

use PHPUnit\Framework\TestCase;
use QrStudio\ColorContrast;

class ColorContrastTest extends TestCase
{
    public function test_short_and_long_hex_colours_normalize_to_lowercase_rrggbb(): void
    {
        $this->assertSame('#ffffff', ColorContrast::normalizeHex('#fff'));
        $this->assertSame('#1f3d7a', ColorContrast::normalizeHex('#1F3D7A'));
        $this->assertSame('#cc2244', ColorContrast::normalizeHex(' #c24 '));
    }

    public function test_values_that_are_not_hex_colours_are_rejected(): void
    {
        foreach (['red', '#ff00', '#gggggg', 'url(https://example.com)', '', null, 123] as $value) {
            $this->assertNull(ColorContrast::normalizeHex($value));
            $this->assertFalse(ColorContrast::isHexColor($value));
        }
    }

    public function test_rgb_reads_short_hex_correctly(): void
    {
        $this->assertSame([255, 255, 255], ColorContrast::rgb('#fff'));
        $this->assertSame([31, 61, 122], ColorContrast::rgb('#1f3d7a'));
    }

    public function test_contrast_ratio_matches_wcag(): void
    {
        $this->assertEqualsWithDelta(21.0, ColorContrast::contrastRatio('#fff', '#000'), 0.01);
        $this->assertEqualsWithDelta(21.0, ColorContrast::contrastRatio('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, ColorContrast::contrastRatio('#777', '#777777'), 0.0001);
    }
}
