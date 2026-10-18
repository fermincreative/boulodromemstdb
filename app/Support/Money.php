<?php

namespace App\Support;

/**
 * Amounts are stored as integer cents and shown the Dutch way: "€ 67,50".
 */
final class Money
{
    public static function format(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Reads "67,50", "67.50", "€ 67,50", "1.234,50" or "90" into cents; null when it is not an amount.
     */
    public static function parse(?string $input): ?int
    {
        $value = str_replace(['€', ' '], '', trim((string) $input));

        if ($value === '' || ! preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?$|^\d+(?:[.,]\d{1,2})?$/', $value)) {
            return null;
        }

        // A comma is the decimal separator; dots before it are thousands separators.
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return (int) round(((float) $value) * 100);
    }

    /**
     * For form fields: 6750 → "67,50".
     */
    public static function toInput(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, ',', '');
    }
}
