<?php

return [

    'association_name' => 'Boulodrôme Maastricht',

    /*
    | Secret used to sign the QR payload. Must stay identical to the one used by the old
    | ledenpas tool, otherwise cards that were already sent no longer verify.
    */
    'qr_secret' => env('QR_SECRET'),

    /*
    | Base URL the QR code points to. Defaults to this app's public verify page.
    | Old cards point to ".../verify.php"; that path is served too.
    */
    'verify_url' => env('CARD_VERIFY_URL'),

    'logo_path' => resource_path('card/logo.png'),

    // Card colours (hex).
    'colors' => [
        'header' => '#000000',
        'accent' => '#E5D0A7',
        'accent_text' => '#AC9C7D',
        'text' => '#111111',
        'muted' => '#7A7A7A',
        'line' => '#E0E0E0',
    ],

    // Clubs that are not printed on the card.
    'hidden_clubs' => ['Geen', 'Onbekend'],

];
