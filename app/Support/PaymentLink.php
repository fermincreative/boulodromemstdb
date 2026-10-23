<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Support\Facades\URL;

/**
 * Signed links to the public payment page of an open payment (in e-mails), and Mollie's way back.
 * The signature keeps people from guessing other members' payment pages; the links do not expire.
 */
class PaymentLink
{
    public static function for(Payment $payment): string
    {
        return URL::signedRoute('payment.show', ['payment' => $payment]);
    }

    public static function start(Payment $payment): string
    {
        return URL::signedRoute('payment.start', ['payment' => $payment]);
    }

    public static function return(Payment $payment): string
    {
        return URL::signedRoute('payment.return', ['payment' => $payment]);
    }
}
