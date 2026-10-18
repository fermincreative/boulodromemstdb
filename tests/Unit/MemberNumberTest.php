<?php

use App\Support\MemberNumber;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(fn () => config(['members.number_prefix' => 'SBM', 'members.number_digits' => 4]));

it('formats numbers with prefix, padding and MOD 97-10 check digits', function (int $number, string $expected) {
    expect(MemberNumber::format($number))->toBe($expected);
})->with([
    [1, 'SBM-0001-95'],
    [2, 'SBM-0002-92'],
    [261, 'SBM-0261-91'],
    [9999, 'SBM-9999-74'],
    [12345, 'SBM-12345-'.MemberNumber::checkDigits(12345)],
]);

it('has check digits that satisfy ISO 7064 MOD 97-10', function () {
    foreach (range(1, 2000) as $number) {
        expect((int) ($number.MemberNumber::checkDigits($number)) % 97)->toBe(1);
    }
});

it('reads numbers as people type them', function (string $input, ?int $expected) {
    expect(MemberNumber::parse($input))->toBe($expected);
})->with([
    ['SBM-0261-91', 261],
    ['sbm-0261-91', 261],
    ['SBM 0261 91', 261],
    ['0261-91', 261],
    ['261', 261],
    ['SBM-0261-19', null],
    ['SBM-0216-91', null],
    ['SBM-0261', null],
    ['ABC-0261-91', null],
    ['', null],
]);

it('detects every single-digit typo and neighbouring swap', function () {
    foreach ([7, 261, 1234, 4321] as $number) {
        $formatted = str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        $check = MemberNumber::checkDigits($number);

        for ($i = 0; $i < 4; $i++) {
            foreach (range(0, 9) as $digit) {
                $typo = substr_replace($formatted, (string) $digit, $i, 1);
                if ($typo !== $formatted) {
                    expect(MemberNumber::parse("SBM-{$typo}-{$check}"))->toBeNull();
                }
            }

            if ($i < 3 && $formatted[$i] !== $formatted[$i + 1]) {
                $swap = $formatted;
                [$swap[$i], $swap[$i + 1]] = [$swap[$i + 1], $swap[$i]];
                expect(MemberNumber::parse("SBM-{$swap}-{$check}"))->toBeNull();
            }
        }
    }
});
