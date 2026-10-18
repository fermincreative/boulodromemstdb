<?php

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    actingAs(User::factory()->admin()->create());
    travelTo(now()->setDate(2026, 10, 18));
});

function createMember(array $data)
{
    return Livewire::test(CreateMember::class)
        ->fillForm(['first_name' => 'Mees', 'last_name' => 'Jonkman', ...$data])
        ->call('create');
}

it('accepts a typed birthday in any common notation', function (string $typed) {
    createMember(['birthday' => $typed])->assertHasNoFormErrors();

    expect(Member::sole()->birthday->toDateString())->toBe('1992-09-10');
})->with(['10-09-1992', '10/9/1992', '10.9.92', '10091992']);

it('tidies up a typed date when leaving the field', function () {
    Livewire::test(CreateMember::class)
        ->fillForm(['birthday' => '10/9/92'])
        ->assertSchemaStateSet(['birthday' => '10-09-1992']);
});

it('rejects impossible and future birthdays', function (string $typed, string $message) {
    createMember(['birthday' => $typed])->assertHasFormErrors(['birthday']);

    Livewire::test(CreateMember::class)
        ->fillForm(['first_name' => 'Mees', 'last_name' => 'Jonkman', 'birthday' => $typed])
        ->call('create')
        ->assertSee($message);
})->with([
    ['31-02-1992', 'Vul een geldige datum in'],
    ['gisteren', 'Vul een geldige datum in'],
    ['01-01-2030', 'De datum mag niet na 18-10-2026 liggen'],
    ['01-01-1850', 'De datum mag niet voor 01-01-1900 liggen'],
]);

it('fills the birthday from the calendar button', function () {
    Livewire::test(CreateMember::class)
        ->callAction(TestAction::make('birthdayPicker')->schemaComponent('birthday'), ['date' => '1992-09-10'])
        ->assertSchemaStateSet(['birthday' => '10-09-1992']);
});

it('shows a stored birthday as dd-mm-jjjj when editing, on the right day', function () {
    $member = Member::factory()->create(['birthday' => '1992-09-10']);

    Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
        ->assertSchemaStateSet(['birthday' => '10-09-1992'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($member->fresh()->birthday->toDateString())->toBe('1992-09-10');
});

it('validates the e-mail address format', function (string $email, bool $valid) {
    $form = createMember(['email' => $email]);

    $valid ? $form->assertHasNoFormErrors() : $form->assertHasFormErrors(['email']);
})->with([
    ['mees@example.nl', true],
    ['Mees.Jonkman+club@sub.example.com', true],
    ['mees@example', false],
    ['mees@example.c', false],
    ['mees example.nl', false],
    ['mees@@example.nl', false],
    ['mees@exa mple.nl', false],
]);

it('stores e-mail addresses trimmed and in lower case', function () {
    createMember(['email' => '  Mees@Example.NL '])->assertHasNoFormErrors();

    expect(Member::sole()->email)->toBe('mees@example.nl');
});

it('does not show the member number field', function () {
    Livewire::test(CreateMember::class)->assertFormFieldDoesNotExist('member_number');

    Livewire::test(EditMember::class, ['record' => Member::factory()->create()->getRouteKey()])
        ->assertFormFieldDoesNotExist('member_number');
});

it('still assigns member numbers automatically', function () {
    Member::factory()->create(['member_number' => 41]);

    createMember([])->assertHasNoFormErrors();

    expect(Member::firstWhere('last_name', 'Jonkman')->member_number)->toBe(42);
});
