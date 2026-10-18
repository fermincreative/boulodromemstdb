<?php

use App\Actions\Cards\QueueMembershipCard;
use App\Jobs\SendMembershipCard;
use App\Mail\MembershipCardMail;
use App\Models\Club;
use App\Models\Member;
use App\Models\Membership;
use App\Services\MembershipCard;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config([
        'membership_card.qr_secret' => 'test-secret',
        'membership_card.verify_url' => 'https://leden.example/verify',
    ]);
    travelTo(now()->setDate(2026, 6, 1));

    $this->member = Member::factory()->create([
        'member_number' => 261,
        'first_name' => 'Marcella',
        'middle_name' => null,
        'last_name' => 'Lammerts van Beuren',
        'email' => 'marcella@example.com',
        'club_id' => Club::firstWhere('name', 'SBM Halpas')->id,
    ]);
    $this->membership = Membership::factory()->forYear(2026)->for($this->member)->create();
});

/**
 * The query string of the QR code, as an array.
 */
function qrParams(Membership $membership): array
{
    parse_str(parse_url(app(MembershipCard::class)->qrPayload($membership), PHP_URL_QUERY), $params);

    return $params;
}

describe('QR code', function () {
    it('signs exactly like the old ledenpas tool', function () {
        $expected = strtoupper(substr(hash_hmac('sha256', '261|Marcella Lammerts van Beuren|2026', 'test-secret'), 0, 10));

        expect(MembershipCard::signature(261, 'Marcella Lammerts van Beuren', 2026))->toBe($expected);
    });

    it('keeps signing the plain member number, so cards already sent stay valid', function () {
        expect(qrParams($this->membership)['l'])->toBe('261');
    });

    it('encodes the verify url with number, name, year and signature', function () {
        $payload = app(MembershipCard::class)->qrPayload($this->membership);

        expect($payload)->toStartWith('https://leden.example/verify?l=261&n=Marcella%20Lammerts%20van%20Beuren&j=2026&c=')
            ->and(qrParams($this->membership)['c'])->toBe(MembershipCard::signature(261, 'Marcella Lammerts van Beuren', 2026));
    });

    it('refuses to make cards without a secret', function () {
        config(['membership_card.qr_secret' => null]);

        app(MembershipCard::class)->phonePdf($this->membership);
    })->throws(RuntimeException::class);
});

describe('PDF', function () {
    it('renders the phone and print versions', function (string $variant) {
        $card = app(MembershipCard::class);
        $pdf = $card->pdf($this->membership, $variant);

        expect($pdf)->toStartWith('%PDF-')
            ->and(strlen($pdf))->toBeGreaterThan(10_000)
            ->and($card->filename($this->membership, $variant))->toBe(
                'Lidmaatschapskaart-2026-SBM-0261-91-'.($variant === MembershipCard::PHONE ? 'telefoon' : 'print').'.pdf'
            );
    })->with([MembershipCard::PHONE, MembershipCard::PRINT]);

    it('renders members without a club or with special characters', function () {
        $member = Member::factory()->create(['first_name' => 'Zoë', 'middle_name' => "d'", 'last_name' => 'Éloïse-Ünal', 'club_id' => null]);
        $membership = Membership::factory()->forYear(2026)->for($member)->create();

        expect(app(MembershipCard::class)->printPdf($membership))->toStartWith('%PDF-');
    });
});

describe('verify page', function () {
    it('shows a valid card as green', function () {
        get(route('card.verify', qrParams($this->membership)))
            ->assertOk()
            ->assertSee('Geldig lid')
            ->assertSee('Marcella Lammerts van Beuren')
            ->assertSee('Geldig t/m 31-12-2026')
            ->assertSee('SBM-0261-91')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    });

    it('keeps old card links on /verify.php working', function () {
        get('/verify.php?'.http_build_query(qrParams($this->membership)))
            ->assertOk()
            ->assertSee('Geldig lid');
    });

    it('rejects a tampered card', function () {
        get(route('card.verify', ['n' => 'Iemand Anders'] + qrParams($this->membership)))
            ->assertOk()
            ->assertSee('Ongeldige kaart')
            ->assertDontSee('Geldig lid');
    });

    it('rejects an incomplete QR code', function () {
        get(route('card.verify', ['l' => 261]))->assertSee('Ongeldige kaart');
    });

    it('rejects an expired membership', function () {
        $params = qrParams($this->membership);
        travelTo(now()->setDate(2027, 1, 15));

        get(route('card.verify', $params))
            ->assertSee('Lidmaatschap verlopen')
            ->assertSee('Verlopen op 31-12-2026');
    });

    it('rejects a membership that is still awaiting payment', function () {
        $this->membership->update(['status' => 'pending']);

        get(route('card.verify', qrParams($this->membership)))->assertSee('Lidmaatschap verlopen');
    });

    it('rejects a cancelled member', function () {
        $params = qrParams($this->membership);
        $this->member->update(['cancelled_at' => '2026-05-01']);

        get(route('card.verify', $params))->assertSee('Lidmaatschap opgezegd');
    });

    it('accepts last year\'s card when the membership was renewed, with a notice', function () {
        $old = Membership::factory()->forYear(2025)->for($this->member)->create();

        get(route('card.verify', qrParams($old)))
            ->assertSee('Geldig lid')
            ->assertSee('Let op: dit is een oude kaart (2025)');
    });

    it('shows the furthest end date when next year is already paid', function () {
        // Renewed member scanning the new 2027 card while 2026 is still running (no payments involved).
        $next = Membership::factory()->forYear(2027)->for($this->member)->create();

        get(route('card.verify', qrParams($next)))
            ->assertSee('Geldig lid')
            ->assertSee('Geldig t/m 31-12-2027')
            ->assertDontSee('Let op');
    });

    it('stops at a gap between periods', function () {
        Membership::factory()->for($this->member)->create(['starts_at' => '2027-03-01', 'ends_at' => '2027-12-31']);

        get(route('card.verify', qrParams($this->membership)))->assertSee('Geldig t/m 31-12-2026');
    });

    it('does not count a next period that is not paid yet', function () {
        Membership::factory()->forYear(2027)->pending()->for($this->member)->create();

        get(route('card.verify', qrParams($this->membership)))->assertSee('Geldig t/m 31-12-2026');
    });

    it('does not show personal details beyond name and number', function () {
        get(route('card.verify', qrParams($this->membership)))
            ->assertDontSee('marcella@example.com')
            ->assertDontSee($this->member->street);
    });

    it('says so when the secret is not configured', function () {
        $params = qrParams($this->membership);
        config(['membership_card.qr_secret' => null]);

        get(route('card.verify', $params))->assertSee('Controle niet mogelijk');
    });
});

describe('sending', function () {
    it('queues the card e-mail', function () {
        Queue::fake();

        app(QueueMembershipCard::class)->handle($this->membership);

        Queue::assertPushed(SendMembershipCard::class, fn ($job) => $job->membership->is($this->membership));
    });

    it('mails both PDFs, then marks the card as sent and logs it', function () {
        Mail::fake();

        (new SendMembershipCard($this->membership))->handle();

        Mail::assertSent(MembershipCardMail::class, function (MembershipCardMail $mail) {
            $mail->assertHasSubject('Uw lidmaatschapskaart 2026 — Boulodrôme Maastricht');
            $names = collect($mail->attachments())->map(fn ($attachment) => $attachment->as)->all();
            expect($names)->toBe(['Lidmaatschapskaart-2026-SBM-0261-91-telefoon.pdf', 'Lidmaatschapskaart-2026-SBM-0261-91-print.pdf']);
            $mail->assertSeeInHtml('Beste Marcella Lammerts van Beuren');

            return $mail->hasTo('marcella@example.com') && count($mail->attachments()) === 2;
        });

        expect($this->membership->fresh()->card_sent_at)->not->toBeNull()
            ->and(Activity::where('event', 'card_sent')->exists())->toBeTrue();
    });

    it('does not mail members without e-mail or cancelled members', function (array $changes, string $reason) {
        Queue::fake();
        $this->member->update($changes);

        expect(app(QueueMembershipCard::class)->cannotSendReason($this->membership->fresh()))->toContain($reason);
        expect(fn () => app(QueueMembershipCard::class)->handle($this->membership->fresh()))->toThrow(InvalidArgumentException::class);

        Queue::assertNothingPushed();
    })->with([
        'no e-mail' => [['email' => null], 'geen e-mailadres'],
        'cancelled' => [['cancelled_at' => '2026-05-01'], 'opgezegd'],
    ]);

    it('skips the mail when the member left after queueing', function () {
        Mail::fake();
        $this->member->update(['cancelled_at' => today()->subDay()]);

        (new SendMembershipCard($this->membership))->handle();

        Mail::assertNothingSent();
        expect($this->membership->fresh()->card_sent_at)->toBeNull();
    });
});
