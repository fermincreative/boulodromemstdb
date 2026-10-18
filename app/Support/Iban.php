<?php

namespace App\Support;

/**
 * IBAN check (ISO 13616, mod 97) and the usual notation in groups of four: "NL96 ABNA 0617 0464 84".
 */
final class Iban
{
    public static function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }

    public static function isValid(?string $iban): bool
    {
        $iban = self::normalize((string) $iban);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        $digits = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
