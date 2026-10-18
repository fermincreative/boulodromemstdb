<?php

use App\Actions\Members\ExportMembersCsv;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Club;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * Runs the export and returns the CSV rows (without BOM) as arrays.
 */
function exportRows($query): array
{
    $response = app(ExportMembersCsv::class)->download($query, 'test');

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toStartWith("\xEF\xBB\xBF");

    return array_map(fn ($line) => str_getcsv($line, ';', '"', ''), array_filter(explode("\n", substr($csv, 3))));
}

it('exports members with Dutch headers and dates', function () {
    $member = Member::factory()->create([
        'member_number' => 261, 'first_name' => 'Marcella', 'middle_name' => 'van', 'last_name' => 'Beuren',
        'birthday' => '1960-03-09', 'postal_code' => '3620', 'phone_mobile' => '+31 6 1234 5678',
        'club_id' => Club::firstWhere('name', 'SBM Halpas')->id, 'notes' => "Twee\nregels; met puntkomma",
    ]);
    Membership::factory()->forYear(2026)->for($member)->create();

    [$header, $row] = exportRows(Member::query());
    $data = array_combine($header, $row);

    expect($header)->toContain('Lidnummer', 'Achternaam', 'Geldig t/m', 'Opgezegd op')
        ->and($data['Lidnummer'])->toBe('SBM-0261-91')
        ->and($data['Tussenvoegsel'])->toBe('van')
        ->and($data['Verjaardag'])->toBe('09-03-1960')
        ->and($data['Postcode'])->toBe('3620')
        ->and($data['Mobiel'])->toBe('+31 6 1234 5678')
        ->and($data['Vereniging'])->toBe('SBM Halpas')
        ->and($data['Geldig t/m'])->toBe('31-12-2026');
});

it('keeps notes with line breaks and semicolons in one cell', function () {
    Member::factory()->create(['notes' => "Twee\nregels; met puntkomma"]);

    $response = app(ExportMembersCsv::class)->download(Member::query(), 'test');
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain("\"Twee\nregels; met puntkomma\"");
});

it('neutralises spreadsheet formulas', function () {
    Member::factory()->create(['first_name' => '=HYPERLINK("http://evil")', 'last_name' => '@SUM(A1)']);

    [$header, $row] = exportRows(Member::query());
    $data = array_combine($header, $row);

    expect($data['Voornaam'])->toBe('\'=HYPERLINK("http://evil")')
        ->and($data['Achternaam'])->toBe("'@SUM(A1)");
});

it('exports what the table shows and logs the export', function () {
    actingAs(User::factory()->admin()->create());
    Member::factory()->count(3)->create();
    Member::factory()->cancelled()->create();

    Livewire::test(ListMembers::class)
        ->callAction('exportCsv')
        ->assertFileDownloaded('leden-'.now()->format('Y-m-d').'.csv');

    $log = Activity::where('event', 'exported')->sole();
    expect($log->properties['count'])->toBe(3)
        ->and($log->causer)->not->toBeNull();
});

it('exports only the selected members', function () {
    actingAs(User::factory()->admin()->create());
    $members = Member::factory()->count(4)->create();

    Livewire::test(ListMembers::class)
        ->callTableBulkAction('exportSelectedCsv', $members->take(2))
        ->assertFileDownloaded();

    expect(Activity::where('event', 'exported')->sole()->properties['count'])->toBe(2);
});

it('does not let treasurers or viewers export', function (string $role) {
    actingAs(User::factory()->{$role}()->create());

    Livewire::test(ListMembers::class)
        ->assertActionHidden('exportCsv')
        ->assertTableBulkActionHidden('exportSelectedCsv');
})->with(['treasurer', 'viewer']);
