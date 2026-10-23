<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\LinkMemberAccount;
use App\Actions\Accounts\LogInWithLink;
use App\Actions\Accounts\SendLoginLink;
use App\Actions\Members\ConfirmEmailChange;
use App\Actions\Members\UpdateContactDetails;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\UpdateContactDetailsRequest;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Membership;
use App\Models\ScheduleSlot;
use App\Services\MembershipCard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Illuminate\Support\defer;

/**
 * The ledenportaal on the main domain: log in with a link by e-mail, then see your own membership(s),
 * download your card and pay what is open. Everything is looked up through the logged-in account.
 */
class MemberPortalController extends Controller
{
    /** How many days of the Rooster the overview shows (today included). */
    private const UPCOMING_DAYS = 4;

    public function home(Request $request, MembershipCard $card): View
    {
        $account = $this->account();

        if (! $account) {
            return view('portal.login', ['sent' => $request->session()->get('login_link_sent')]);
        }

        $account->load(['members' => fn ($members) => $members->with([
            'club',
            'memberships' => fn ($memberships) => $memberships->with('member')->orderByDesc('starts_at'),
            'payments' => fn ($payments) => $payments->where('status', PaymentStatus::Open)->latest('id'),
        ])]);

        return view('portal.dashboard', [
            'account' => $account,
            'members' => $account->members->map(fn (Member $member) => $this->overview($member, $card)),
            'upcoming' => $this->scheduleDays(CarbonImmutable::today(), self::UPCOMING_DAYS),
            'contact' => config('mail.reply_to.address') ?: config('mail.from.address'),
        ]);
    }

    public function requestLink(Request $request, SendLoginLink $send): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']], [], ['email' => 'e-mailadres']);

        $email = $data['email'];
        $ip = $request->ip();

        // After the response has been sent: known and unknown addresses get the same answer in the same time,
        // so response times don't reveal who is a member.
        defer(fn () => $send->handle($email, $ip));

        return redirect()->route('home')->with('login_link_sent', LinkMemberAccount::normalize($data['email']));
    }

    /**
     * Opening the link only shows a button: mail programs that check links by opening them must not use it up.
     */
    public function confirm(string $token): View
    {
        return view('portal.confirm', ['token' => $token, 'usable' => LogInWithLink::isUsable($token)]);
    }

    public function logIn(Request $request, string $token, LogInWithLink $logIn): RedirectResponse
    {
        try {
            $account = $logIn->handle($token);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('home')->withErrors(['email' => $e->getMessage()]);
        }

        // Stays logged in on this device ("remember me"); logging out ends that.
        Auth::guard('member')->login($account, remember: true);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    /**
     * Only the member login ends; a staff login in the same browser (/admin) is left alone.
     */
    public function logOut(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('logged_out', true);
    }

    public function card(Membership $membership, string $variant, MembershipCard $card): Response
    {
        // Not yours, or no card: the same "not found", so nothing is given away about other members.
        abort_unless($membership->member->member_account_id === $this->account()->id && $membership->cardAvailable(), 404);
        abort_unless(in_array($variant, [MembershipCard::PHONE, MembershipCard::PRINT], true), 404);

        try {
            $pdf = $card->pdf($membership, $variant);
        } catch (RuntimeException) {
            return redirect()->route('home')->withErrors(['card' => __('portal.dashboard.card_error')]);
        }

        return response()->streamDownload(fn () => print ($pdf), $card->filename($membership, $variant), ['Content-Type' => 'application/pdf']);
    }

    /**
     * What the overview shows for one member: status, today's card (QR on screen), and what is coming.
     *
     * @return array<string, mixed>
     */
    private function overview(Member $member, MembershipCard $card): array
    {
        $today = $member->membershipToday();
        $cardMembership = $today?->cardAvailable() ? $today : null;

        try {
            $qr = $cardMembership && filled(config('membership_card.qr_secret')) ? $card->qrSvg($cardMembership) : null;
        } catch (RuntimeException) {
            $qr = null;
        }

        return [
            'member' => $member,
            'validToday' => $today !== null,
            'paidUntil' => $member->paidUntil(),
            // A later period that is paid already: its card replaces today's when it starts.
            'nextPaid' => $member->memberships
                ->filter(fn (Membership $membership) => $membership->isActive() && $today && $membership->starts_at->gt($today->ends_at))
                ->sortBy('starts_at')
                ->first(),
            'pending' => $member->memberships->first(fn (Membership $membership) => $membership->status === MembershipStatus::Pending),
            'card' => $cardMembership,
            'qr' => $qr,
        ];
    }

    /**
     * $count days of the Rooster from $from, each with its open parts of the day in time order.
     *
     * @return Collection<int, array{date: CarbonImmutable, slots: Collection<int, ScheduleSlot>}>
     */
    private function scheduleDays(CarbonImmutable $from, int $count): Collection
    {
        $slots = ScheduleSlot::between($from, $from->addDays($count - 1))->orderBy('starts_at')->get()
            ->groupBy(fn (ScheduleSlot $slot) => $slot->date->toDateString());

        return collect(range(0, $count - 1))->map(fn (int $offset) => [
            'date' => $from->addDays($offset),
            'slots' => $slots->get($from->addDays($offset)->toDateString(), collect()),
        ]);
    }

    /**
     * The Rooster for members: when the Boulodrôme is open, from this week on. Event names only when public.
     */
    public function schedule(Request $request): View
    {
        $thisWeek = CarbonImmutable::today()->startOfWeek(CarbonInterface::MONDAY);

        try {
            $requested = $request->query('week') ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('week')) : null;
        } catch (Throwable) {
            $requested = null;
        }

        $start = ($requested ?: $thisWeek)->startOfWeek(CarbonInterface::MONDAY)->max($thisWeek);

        return view('portal.schedule', [
            'account' => $this->account(),
            'start' => $start,
            'days' => $this->scheduleDays($start, 7),
            'previous' => $start->gt($thisWeek) ? $start->subWeek() : null,
            'next' => $start->addWeek(),
        ]);
    }

    public function edit(Member $member): View
    {
        $this->authorizeOwn($member);

        return view('portal.edit', [
            'member' => $member,
            // Others on the same login, who keep the shared address if this member changes theirs.
            'sharedWith' => $member->account?->members->reject(fn (Member $other) => $other->is($member))->pluck('first_name'),
        ]);
    }

    public function update(UpdateContactDetailsRequest $request, Member $member, UpdateContactDetails $update): RedirectResponse
    {
        $this->authorizeOwn($member);

        $confirmationSentTo = $update->handle($member, $request->validated(), $this->account());

        return redirect()->route('home')->with('status', $confirmationSentTo
            ? __('portal.dashboard.email_confirmation_sent', ['email' => e($confirmationSentTo)])
            : e(__('portal.dashboard.saved')));
    }

    /**
     * The link from the confirmation e-mail (signed, 48 hours). Opening it only shows a button, like the login link.
     */
    public function confirmEmail(Request $request, Member $member): View
    {
        return view('portal.confirm-email', [
            'member' => $member,
            'email' => (string) $request->query('email'),
            'valid' => ConfirmEmailChange::applies($member, (string) $request->query('email')),
            'action' => $request->fullUrl(),
        ]);
    }

    public function applyEmail(Request $request, Member $member, ConfirmEmailChange $confirm): RedirectResponse
    {
        $email = (string) $request->query('email');

        try {
            $confirm->handle($member, $email);
        } catch (InvalidArgumentException) {
            return redirect()->to($request->fullUrl());
        }

        return redirect()->route('home')->with('status', e(__('portal.dashboard.email_changed', ['email' => $email])));
    }

    /**
     * Only your own members, and only while they are a member. Anything else: "not found".
     */
    private function authorizeOwn(Member $member): void
    {
        abort_unless($member->member_account_id === $this->account()?->id && ! $member->hasLeft(), 404);
    }

    private function account(): ?MemberAccount
    {
        return Auth::guard('member')->user();
    }
}
