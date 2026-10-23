<?php

namespace App\Actions\Memberships;

enum RenewalOutcome
{
    /** Not due (already renewed, gave notice, or not a member last year): nothing created. */
    case Skipped;

    /** Renewed; the club pays, so there is no payment request. */
    case ViaClub;

    /** Renewed with an open payment, and the renewal e-mail with the payment link is queued. */
    case Mailed;

    /** Renewed with an open payment, but not e-mailed (no e-mail address, or mail switched off). */
    case NotMailed;
}
