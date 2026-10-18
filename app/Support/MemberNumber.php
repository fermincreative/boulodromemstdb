<?php

namespace App\Support;

/**
 * Display format of member numbers: 261 ⇄ "SBM-0261-95".
 *
 * The check digits use ISO 7064 MOD 97-10 (as in IBAN): 98 - (number × 100 mod 97).
 * They catch every single wrong digit and every swap of two neighbouring digits.
 */
final class MemberNumber
{
    public static function checkDigits(int $number): string
    {
        return str_pad((string) (98 - (($number * 100) % 97)), 2, '0', STR_PAD_LEFT);
    }

    public static function format(int $number): string
    {
        $digits = str_pad((string) $number, config('members.number_digits'), '0', STR_PAD_LEFT);

        return config('members.number_prefix')."-{$digits}-".self::checkDigits($number);
    }

    public static function isValidCheck(int $number, string $checkDigits): bool
    {
        return hash_equals(self::checkDigits($number), $checkDigits);
    }

    /**
     * Reads a member number as typed by a person: "SBM-0261-95", "sbm 0261 95", "0261-95" or plain "261".
     * Returns null for anything else, including a full number with wrong check digits.
     */
    public static function parse(string $input): ?int
    {
        $input = strtoupper(trim($input));

        if (preg_match('/^\d{1,9}$/', $input)) {
            return (int) $input;
        }

        $prefix = preg_quote(strtoupper(config('members.number_prefix')), '/');

        if (! preg_match("/^(?:{$prefix})?[\\s-]*(\\d{1,9})[\\s-]+(\\d{2})$/", $input, $m)) {
            return null;
        }

        $number = (int) $m[1];

        return self::isValidCheck($number, $m[2]) ? $number : null;
    }
}
