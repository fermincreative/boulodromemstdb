<?php

return [

    /*
    | Member numbers are shown as PREFIX-NNNN-CC, e.g. SBM-0261-95. The stored member_number (261) never changes;
    | CC are ISO 7064 MOD 97-10 check digits (as in IBAN), so typos and swapped digits are detected.
    */
    'number_prefix' => env('MEMBER_NUMBER_PREFIX', 'SBM'),

    'number_digits' => 4,

    /*
    | Public sign-up form at /aanmelden.
    */
    'signup_enabled' => (bool) env('SIGNUP_ENABLED', true),

    // Who is told about new sign-ups (default: the reply-to address of all e-mails).
    'signup_notify_address' => env('SIGNUP_NOTIFY_ADDRESS'),

    // Spam protection: a form submitted faster than this many seconds is treated as a bot.
    'signup_min_seconds' => 4,

];
