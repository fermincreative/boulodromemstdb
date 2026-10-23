<?php

namespace App\Http\Controllers;

use App\Actions\Cards\VerifyCard;
use App\Actions\Payments\CollectAtEntrance;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Models\User;
use App\Services\Fees;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The page behind the QR code. Everyone sees whether the card is valid; staff who are logged in to the admin
 * panel in the same browser also see the member's details and what they may do (by their role).
 */
class VerifyCardController extends Controller
{
    public function __invoke(Request $request, VerifyCard $verify): Response
    {
        $result = $verify->handle($request->only(['l', 'n', 'j', 'c']));
        $staff = User::loggedInStaff();

        return response()
            ->view('cards.verify', [
                'result' => $result,
                'checkedAt' => now(),
                'staff' => $staff,
                'manage' => $staff && $result->member ? $this->manage($staff, $result->member) : null,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            // Never cached: staff see more than the public on the same address.
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * What staff see and may do for the scanned member.
     *
     * @return array<string, mixed>
     */
    private function manage(User $staff, Member $member): array
    {
        $member->loadMissing(['club', 'payments' => fn ($payments) => $payments->where('status', PaymentStatus::Open)->latest('id')]);
        $year = today()->year;
        $collect = app(CollectAtEntrance::class);

        return [
            'member' => $member,
            'viewUrl' => MemberResource::getUrl('view', ['record' => $member], panel: 'admin'),
            'editUrl' => $staff->can('update', $member) ? MemberResource::getUrl('edit', ['record' => $member], panel: 'admin') : null,
            'payments' => $member->payments->filter(fn ($payment) => $staff->can('markPaid', $payment)),
            'openPayments' => $member->payments,
            'renewYear' => $member->payments->isEmpty() && $staff->can('renew', Member::class) && $collect->canRenew($member, $year) ? $year : null,
            'renewFee' => app(Fees::class)->annualFeeCents(),
        ];
    }
}
