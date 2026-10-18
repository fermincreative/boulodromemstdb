<?php

use App\Filament\Resources\MailTemplates\MailTemplateResource;
use App\Filament\Resources\MailTemplates\Pages\EditMailTemplate;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Jobs\SendMembershipCard;
use App\Mail\MembershipCardMail;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Services\MailTemplates;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    $this->member = Member::factory()->create([
        'first_name' => 'Marcella', 'middle_name' => null, 'last_name' => 'Lammerts van Beuren',
        'member_number' => 261, 'email' => 'marcella@example.com',
    ]);
    $this->membership = Membership::factory()->forYear(2026)->for($this->member)->create();
    $this->admin = User::factory()->admin()->create();
});

it('fills the send dialog with the template text for this member', function () {
    actingAs($this->admin);

    Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
        ->mountAction('sendCard')
        ->assertSchemaStateSet([
            'subject' => 'Uw lidmaatschapskaart 2026 — Boulodrôme Maastricht',
            'body' => app(MailTemplates::class)->renderForMembership(MailTemplate::MEMBERSHIP_CARD, $this->membership)['body'],
        ]);

    $body = app(MailTemplates::class)->renderForMembership(MailTemplate::MEMBERSHIP_CARD, $this->membership)['body'];
    expect($body)->toContain('Beste Marcella Lammerts van Beuren')
        ->toContain('geldig tot en met 31-12-2026')
        ->toContain('Uw lidnummer is SBM-0261-91')
        ->not->toContain('{');
});

it('sends the text as edited in the dialog', function () {
    Queue::fake();
    actingAs($this->admin);

    Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
        ->callAction('sendCard', ['subject' => 'Uw nieuwe kaart', 'body' => "Beste Marcella,\n\nHier is hij!"])
        ->assertHasNoActionErrors();

    Queue::assertPushed(SendMembershipCard::class, fn (SendMembershipCard $job) => $job->subject === 'Uw nieuwe kaart'
        && $job->body === "Beste Marcella,\n\nHier is hij!");
});

it('requires a subject and a message', function () {
    Queue::fake();
    actingAs($this->admin);

    Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
        ->callAction('sendCard', ['subject' => '', 'body' => ''])
        ->assertHasActionErrors(['subject' => 'required', 'body' => 'required']);

    Queue::assertNothingPushed();
});

it('uses the edited default text for new e-mails', function () {
    MailTemplate::forKey(MailTemplate::MEMBERSHIP_CARD)->update([
        'subject' => 'Kaart {jaar} voor {voornaam}',
        'body' => 'Hallo {voornaam}, uw nummer is {lidnummer}.',
    ]);

    $mail = new MembershipCardMail($this->membership);

    expect($mail->mailSubject)->toBe('Kaart 2026 voor Marcella');
    $mail->assertSeeInHtml('Hallo Marcella, uw nummer is SBM-0261-91.');
});

it('renders Markdown but escapes HTML in the message', function () {
    $mail = new MembershipCardMail($this->membership, 'Test', "**Belangrijk**\n\n<script>alert(1)</script>");

    $mail->assertSeeInHtml('>Belangrijk</strong>', false)
        ->assertDontSeeInHtml('<script>', false)
        ->assertSeeInHtml('&lt;script&gt;', false);
});

it('lets admins edit the default text and logs it', function () {
    actingAs($this->admin);
    $template = MailTemplate::forKey(MailTemplate::MEMBERSHIP_CARD);

    Livewire::test(EditMailTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm(['subject' => 'Nieuw onderwerp {jaar}'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->subject)->toBe('Nieuw onderwerp {jaar}')
        ->and(Activity::where('log_name', 'settings')->exists())->toBeTrue();
});

it('keeps the e-mail texts for admins only', function (string $role) {
    actingAs(User::factory()->{$role}()->create());

    get(MailTemplateResource::getUrl())->assertForbidden();
})->with(['treasurer', 'viewer']);

it('shows the club name instead of the app name in the e-mail layout', function () {
    $mail = new MembershipCardMail($this->membership);

    $mail->assertSeeInHtml('© '.date('Y').' Boulodrôme Maastricht', false)
        ->assertDontSeeInHtml('All rights reserved');
});
