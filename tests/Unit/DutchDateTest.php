<?php

use App\Support\DutchDate;
use Tests\TestCase;

use function Pest\Laravel\travelTo;

uses(TestCase::class);

beforeEach(fn () => travelTo(now()->setDate(2026, 10, 18)));

it('reads the ways people type a date', function (string $input, ?string $expected) {
    expect(DutchDate::format(DutchDate::parse($input)))->toBe($expected);
})->with([
    ['10-09-1992', '10-09-1992'],
    ['10/9/1992', '10-09-1992'],
    ['10.9.1992', '10-09-1992'],
    ['10 9 1992', '10-09-1992'],
    [' 10 - 9 - 1992 ', '10-09-1992'],
    ['10-9-92', '10-09-1992'],
    ['1-1-05', '01-01-2005'],
    ['10091992', '10-09-1992'],
    ['100992', '10-09-1992'],
    ['1992-09-10', '10-09-1992'],
    ['1992-09-10 00:00:00', '10-09-1992'],
    ['29-02-2024', '29-02-2024'],
    ['29-02-2023', null],
    ['31-04-1992', null],
    ['10-13-1992', null],
    ['september 1992', null],
    ['', null],
]);

it('reads two-digit years as the most recent year that is not in the future', function () {
    expect(DutchDate::parse('1-1-26')->year)->toBe(2026)
        ->and(DutchDate::parse('1-1-27')->year)->toBe(1927);
});
