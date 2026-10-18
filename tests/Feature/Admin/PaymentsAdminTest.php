<?php

use App\Actions\Members\CreateMember;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Filament\Pages\Rates;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\JoiningRate;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\Fees;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 18));
});

function openPaymentFor(string $lastName): Payment
{
    return app(CreateMember::class)
        ->handle(['first_name' => 'Lid', 'last_name' => $lastName, 'email' => strtolower($lastName).'@example.nl'], withMembership: true)
        ->payments()->sole();
}

it('lists open payments by default', function () {
    actingAs(User::factory()->treasurer()->create());
    $open = openPaymentFor('Open');
    $paid = openPaymentFor('Betaald');
    $paid->update(['status' => PaymentStatus::Paid]);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$paid])
        ->assertSee('€ 22,50');
});

it('lets a treasurer mark a payment as paid', function () {
    actingAs(User::factory()->treasurer()->create());
    $payment = openPaymentFor('Jansen');

    Livewire::test(ListPayments::class)
        ->callTableAction('markPaid', $payment, ['method' => 'bank_transfer', 'paid_at' => '17-10-2026', 'send_card' => true])
        ->assertHasNoTableActionErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->paid_at->toDateString())->toBe('2026-10-17')
        ->and($payment->memberships()->sole()->status)->toBe(MembershipStatus::Active);
});

it('marks several payments as paid at once', function () {
    actingAs(User::factory()->treasurer()->create());
    $payments = collect([openPaymentFor('Een'), openPaymentFor('Twee')]);

    Livewire::test(ListPayments::class)
        ->callTableBulkAction('markPaidBulk', $payments, ['method' => 'cash', 'paid_at' => '18-10-2026', 'send_card' => false, 'send_confirmation' => false]);

    expect(Payment::where('status', PaymentStatus::Paid)->count())->toBe(2);
    Queue::assertNothingPushed();
});

it('does not let viewers register payments', function () {
    actingAs(User::factory()->viewer()->create());
    $payment = openPaymentFor('Jansen');

    get(PaymentResource::getUrl())->assertOk();
    Livewire::test(ListPayments::class)
        ->assertTableActionHidden('markPaid', $payment)
        ->assertTableBulkActionHidden('markPaidBulk');
});

it('shows the number of open payments in the menu', function () {
    openPaymentFor('Een');
    openPaymentFor('Twee');

    expect(PaymentResource::getNavigationBadge())->toBe('2');
});

it('filters members awaiting payment', function () {
    actingAs(User::factory()->admin()->create());
    $waiting = openPaymentFor('Wachtend')->member;
    $other = Member::factory()->create();

    Livewire::test(ListMembers::class)
        ->filterTable('status', 'awaiting_payment')
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$other]);
});

describe('Tarieven page', function () {
    it('shows the current rates', function () {
        actingAs(User::factory()->admin()->create());

        Livewire::test(Rates::class)
            ->assertSchemaStateSet(['annual_fee' => '90,00'])
            ->assertSee('Instaptarieven nieuwe leden');
    });

    it('saves changed rates for new payments and logs it', function () {
        actingAs(User::factory()->admin()->create());
        $existing = openPaymentFor('Bestaand');

        Livewire::test(Rates::class)
            ->set('data.annual_fee', '95,00')
            ->set('data.joining_rates', [
                ['day' => 1, 'month' => 1, 'amount' => '95', 'includes_next_year' => false],
                ['day' => 1, 'month' => 7, 'amount' => '50,00', 'includes_next_year' => false],
                ['day' => 15, 'month' => 11, 'amount' => '12,50', 'includes_next_year' => true],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $fees = app(Fees::class);
        expect($fees->annualFeeCents())->toBe(9500)
            ->and($fees->quoteForNewMember(CarbonImmutable::parse('2026-10-18'))->totalCents())->toBe(5000)
            ->and($fees->quoteForNewMember(CarbonImmutable::parse('2026-11-20'))->totalCents())->toBe(1250 + 9500)
            ->and($existing->fresh()->amount_cents)->toBe(2250)
            ->and(Activity::where('event', 'rates_updated')->exists())->toBeTrue();
    });

    it('requires a rate starting on 1 January', function () {
        actingAs(User::factory()->admin()->create());

        Livewire::test(Rates::class)
            ->set('data.joining_rates', [['day' => 1, 'month' => 4, 'amount' => '67,50', 'includes_next_year' => false]])
            ->call('save')
            ->assertHasErrors(['data.joining_rates']);

        expect(JoiningRate::count())->toBe(5);
    });

    it('rejects impossible dates and amounts', function (array $row, string $field) {
        actingAs(User::factory()->admin()->create());

        Livewire::test(Rates::class)
            ->set('data.joining_rates', [['day' => 1, 'month' => 1, 'amount' => '90', 'includes_next_year' => false], $row])
            ->call('save')
            ->assertHasErrors([$field]);
    })->with([
        'feb 31' => [['day' => 31, 'month' => 2, 'amount' => '10', 'includes_next_year' => false], 'data.joining_rates'],
        'text' => [['day' => 1, 'month' => 4, 'amount' => 'tien', 'includes_next_year' => false], 'data.joining_rates.1.amount'],
    ]);

    it('is for admins only', function (string $role) {
        actingAs(User::factory()->{$role}()->create());

        get(Rates::getUrl())->assertForbidden();
    })->with(['treasurer', 'viewer']);
});
