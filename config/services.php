<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    // Amazon SES (MAIL_MAILER=ses). Use an IAM user that may only send mail (ses:SendRawEmail).
    'ses' => [
        'key' => env('SES_KEY') ?: env('AWS_ACCESS_KEY_ID'),
        'secret' => env('SES_SECRET') ?: env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('SES_REGION') ?: env('AWS_DEFAULT_REGION', 'eu-west-1'),
        'options' => array_filter([
            // Optional configuration set, e.g. for bounce and complaint notifications.
            'ConfigurationSetName' => env('SES_CONFIGURATION_SET'),
        ]),
    ],

    // Mollie (iDEAL, Bancontact). Use a test_… key to try it out; payments then show up under "Testmodus" in the Mollie dashboard.
    'mollie' => [
        'key' => env('MOLLIE_KEY'),
        // Comma-separated Mollie method ids offered at checkout, e.g. "ideal,bancontact". Each must be activated in the Mollie dashboard.
        'methods' => env('MOLLIE_METHODS', 'ideal,bancontact'),
        // Where Mollie reports status changes (default: APP_URL/mollie/webhook). Mollie cannot reach localhost.
        'webhook_url' => env('MOLLIE_WEBHOOK_URL'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
