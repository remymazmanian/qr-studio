<?php

namespace QrStudio;

class ColorContrast
{
    public static function isHexColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    public static function contrastRatio(string $first, string $second): float
    {
        $l1 = self::relativeLuminance($first) + 0.05;
        $l2 = self::relativeLuminance($second) + 0.05;

        return max($l1, $l2) / min($l1, $l2);
    }

    public static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);

        $channels = array_map(function (int $value): float {
            $scaled = $value / 255;

            return $scaled <= 0.03928
                ? $scaled / 12.92
                : (($scaled + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
