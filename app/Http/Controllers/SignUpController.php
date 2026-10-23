<?php

namespace App\Http\Controllers;

use App\Actions\Members\SignUpMember;
use App\Actions\Payments\StartOnlinePayment;
use App\Http\Requests\SignUpRequest;
use App\Models\Club;
use App\Models\Setting;
use App\Services\Fees;
use App\Services\Mollie\Mollie;
use App\Support\Money;
use App\Support\PaymentLink;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Public sign-up form at /aanmelden.
 */
class SignUpController extends Controller
{
    public function create(Fees $fees, Mollie $mollie): View
    {
        return view('signup.form', [
            'enabled' => config('members.signup_enabled'),
            'signUpClub' => Club::signUpClub()?->name,
            'collectiveClubs' => Club::collectiveNames('of'),
            'payOnline' => $mollie->isConfigured(),
            'onlineMethods' => $mollie->methodsLabel(),
            'quote' => $fees->quoteForNewMember(),
            // When the form was shown (encrypted), to spot bots that submit instantly.
            'formToken' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function store(SignUpRequest $request, SignUpMember $signUp, StartOnlinePayment $startPayment, Mollie $mollie): RedirectResponse
    {
        abort_unless(config('members.signup_enabled'), 404);

        // Bots: pretend all went well, but create nothing.
        if ($this->looksLikeABot($request)) {
            return redirect()->route('signup.thanks');
        }

        $member = $signUp->handle($request->memberAttributes());
        $payment = SignUpMember::paymentFor($member);

        // Straight on to iDEAL; the thank-you page (with bank details and the payment link) is the fallback.
        if ($mollie->isConfigured()) {
            try {
                return redirect()->away($startPayment->handle($payment), 303);
            } catch (RequestException $e) {
                Log::error('Could not start Mollie payment after sign-up', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->route('signup.thanks')->with('signup', [
            'name' => $member->first_name,
            'email' => $member->email,
            'number' => $member->display_number,
            'amount' => Money::format($payment->amount_cents),
            'description' => $payment->description,
            'iban' => Setting::get(Setting::BANK_IBAN),
            'holder' => Setting::get(Setting::BANK_ACCOUNT_HOLDER),
            'reference' => 'Contributie '.today()->year.' '.$member->display_number,
            'pay_url' => $mollie->isConfigured() ? PaymentLink::for($payment) : null,
            'online_methods' => $mollie->methodsLabel(),
        ]);
    }

    public function thanks(Request $request): View
    {
        return view('signup.thanks', ['signup' => $request->session()->get('signup')]);
    }

    private function looksLikeABot(Request $request): bool
    {
        // A field people never see; bots fill in everything.
        if (filled($request->input('website'))) {
            return true;
        }

        try {
            $shownAt = (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (DecryptException) {
            return true;
        }

        return now()->timestamp - $shownAt < config('members.signup_min_seconds');
    }
}
