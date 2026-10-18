<?php

use App\Actions\Cards\QueueMembershipCard;
use App\Actions\Members\CancelMember;
use App\Actions\Memberships\AddMembership;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Jobs\SendMemberMail;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Services\MembershipCard;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 18));
    actingAs(User::factory()->admin()->create());
    $this->member = Member::factory()->create(['email' => 'lid@example.nl', 'first_name' => 'Mees', 'middle_name' => null, 'last_name' => 'Jonkman']);
    $this->membership = Membership::factory()->forYear(2026)->for($this->member)->create();
});

describe('default date', function () {
    it('is 31 December of this year', function () {
        expect(CancelMember::defaultDate($this->member)->toDateString())->toBe('2026-12-31');
    });

    it('is the end of next year when that is already paid', function () {
        Membership::factory()->forYear(2027)->for($this->member)->create();

        expect(CancelMember::defaultDate($this->member)->toDateString())->toBe('2027-12-31');
    });

    it('ignores a renewal that is not paid yet', function () {
        Membership::factory()->forYear(2027)->pending()->for($this->member)->create();

        expect(CancelMember::defaultDate($this->member)->toDateString())->toBe('2026-12-31');
    });

    it('is this year end for members without an active period', function () {
        $member = Member::factory()->create();

        expect(CancelMember::defaultDate($member)->toDateString())->toBe('2026-12-31');
    });
});

describe('dialog', function () {
    it('proposes the end of the year, also in the e-mail', function () {
        Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
            ->mountAction('cancel')
            ->assertSchemaStateSet(['cancelled_at' => '31-12-2026'])
            ->assertActionDataSet(fn (array $data): bool => str_contains($data['body'], 'opgezegd per 31-12-2026'));
    });

    it('refreshes the e-mail when another date is typed', function () {
        Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
            ->mountAction('cancel')
            ->setActionData(['cancelled_at' => '1-11-2026'])
            ->assertActionDataSet(fn (array $data): bool => str_contains($data['body'], 'opgezegd per 01-11-2026')
                && ! str_contains($data['body'], '31-12-2026'));
    });

    it('refreshes the e-mail when a date is picked from the calendar', function () {
        Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
            ->mountAction('cancel')
            ->callAction(TestAction::make('cancelled_atPicker')->schemaComponent('cancelled_at', 'mountedActionSchema0'), ['date' => '2026-11-15'])
            ->assertActionDataSet(fn (array $data): bool => $data['cancelled_at'] === '15-11-2026'
                && str_contains($data['body'], 'opgezegd per 15-11-2026'));
    });

    it('updates only the date in a hand-edited e-mail', function () {
        Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
            ->mountAction('cancel')
            ->setActionData(['body' => 'Beste Mees, tot ziens per 31-12-2026. Groet!'])
            ->setActionData(['cancelled_at' => '30-11-2026'])
            ->assertActionDataSet(['body' => 'Beste Mees, tot ziens per 30-11-2026. Groet!'])
            ->callMountedAction();

        expect($this->member->fresh()->cancelled_at->toDateString())->toBe('2026-11-30');
        Queue::assertPushed(SendMemberMail::class, fn ($job) => $job->body === 'Beste Mees, tot ziens per 30-11-2026. Groet!');
    });
});

describe('until the cancellation date', function () {
    beforeEach(fn () => app(CancelMember::class)->handle($this->member));

    it('the member is still active, and listed as leaving', function () {
        expect($this->member->fresh()->hasLeft())->toBeFalse();

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$this->member])
            ->assertSee('Stopt per 31-12-2026')
            ->filterTable('status', 'cancelled')
            ->assertCanNotSeeTableRecords([$this->member]);
    });

    it('the card is still valid at the door, and not after', function () {
        parse_str(parse_url(app(MembershipCard::class)->qrPayload($this->membership), PHP_URL_QUERY), $params);

        get(route('card.verify', $params))->assertSee('Geldig lid');

        travelTo(CarbonImmutable::parse('2027-01-02'));
        get(route('card.verify', $params))->assertSee('Lidmaatschap opgezegd');
    });

    it('the card for the paid year can still be sent, but not for later periods', function () {
        $later = Membership::factory()->forYear(2027)->for($this->member)->create();

        expect(app(QueueMembershipCard::class)->cannotSendReason($this->membership->fresh()))->toBeNull()
            ->and(app(QueueMembershipCard::class)->cannotSendReason($later))->toContain('opgezegd');
    });
});

it('withdraws an unpaid renewal after the cancellation date', function () {
    $renewal = app(AddMembership::class)->handle($this->member, CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2027-12-31'), 9000);

    app(CancelMember::class)->handle($this->member);

    expect($renewal->fresh()->status)->toBe(MembershipStatus::Cancelled)
        ->and($renewal->payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});
