<?php

namespace App\Http\Controllers;

use App\Actions\Members\SignUpMember;
use App\Http\Requests\SignUpRequest;
use App\Models\Club;
use App\Models\Setting;
use App\Services\Fees;
use App\Support\Money;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * Public sign-up form at /aanmelden.
 */
class SignUpController extends Controller
{
    public function create(Fees $fees): View
    {
        return view('signup.form', [
            'enabled' => config('members.signup_enabled'),
            'clubs' => Club::orderBy('name')->pluck('name', 'id'),
            'quote' => $fees->quoteForNewMember(),
            // When the form was shown (encrypted), to spot bots that submit instantly.
            'formToken' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function store(SignUpRequest $request, SignUpMember $signUp): RedirectResponse
    {
        abort_unless(config('members.signup_enabled'), 404);

        // Bots: pretend all went well, but create nothing.
        if ($this->looksLikeABot($request)) {
            return redirect()->route('signup.thanks');
        }

        $member = $signUp->handle($request->memberAttributes());
        $payment = SignUpMember::paymentFor($member);

        return redirect()->route('signup.thanks')->with('signup', [
            'name' => $member->first_name,
            'email' => $member->email,
            'number' => $member->display_number,
            'amount' => Money::format($payment->amount_cents),
            'description' => $payment->description,
            'iban' => Setting::get(Setting::BANK_IBAN),
            'holder' => Setting::get(Setting::BANK_ACCOUNT_HOLDER),
            'reference' => 'Contributie '.today()->year.' '.$member->display_number,
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
