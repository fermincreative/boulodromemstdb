<?php

namespace App\Support;

/**
 * Small helpers for #RRGGBB colours.
 */
final class Color
{
    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function isHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    /**
     * WCAG contrast ratio between two colours (1 = identical, 21 = black on white).
     */
    public static function contrast(string $a, string $b): float
    {
        [$light, $dark] = [self::luminance($a), self::luminance($b)];

        if ($dark > $light) {
            [$light, $dark] = [$dark, $light];
        }

        return ($light + 0.05) / ($dark + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
