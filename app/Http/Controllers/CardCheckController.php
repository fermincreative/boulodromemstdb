<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CollectAtEntrance;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Staff actions on the card check page (/verify) after scanning a card. Staff are recognised by their
 * admin login (guard "web") in the same browser; permissions are the same as in the admin panel.
 */
class CardCheckController extends Controller
{
    /**
     * "Beheerder? Inloggen": the normal admin login (with two-step verification), then back to the scanned card.
     */
    public function logIn(Request $request): RedirectResponse
    {
        $request->session()->put('url.intended', $this->returnUrl($request));

        return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
    }

    public function collect(Request $request, Payment $payment, CollectAtEntrance $collect): RedirectResponse
    {
        abort_unless(User::loggedInStaff()?->can('markPaid', $payment), 403);

        $collect->payment($payment);

        return redirect()->to($this->returnUrl($request))->with('card_check', 'Contant ontvangen: '.Money::format($payment->amount_cents).'. Het lidmaatschap is actief.');
    }

    public function renew(Request $request, Member $member, CollectAtEntrance $collect): RedirectResponse
    {
        abort_unless(User::loggedInStaff()?->can('renew', Member::class), 403);

        $year = today()->year;

        try {
            $payment = $collect->renewAndCollect($member, $year);
        } catch (InvalidArgumentException $e) {
            return redirect()->to($this->returnUrl($request))->with('card_check_error', $e->getMessage());
        }

        return redirect()->to($this->returnUrl($request))->with('card_check', "Verlengd voor {$year} en contant ontvangen: ".Money::format($payment->amount_cents).'.');
    }

    /**
     * Only back to a card check page of this site (never an outside address).
     */
    private function returnUrl(Request $request): string
    {
        $url = (string) $request->input('return');

        return Str::startsWith($url, [url('/verify?'), url('/verify.php?')]) ? $url : url('/verify');
    }
}
