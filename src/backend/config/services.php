<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ── WhatsApp (D-006 LOCKED 2026-04-10) ────────────────────────────────────
    // Driver: 'mock' (local dev default) | 'msg91' (Indian gateway for testing)
    // For msg91: sign up at https://msg91.com, create a WhatsApp template,
    // add credentials to .env:
    //   WHATSAPP_DRIVER=msg91
    //   MSG91_AUTH_KEY=...
    //   MSG91_INTEGRATED_NUMBER=91XXXXXXXXXX
    //   MSG91_TEMPLATE_ID=...
    'whatsapp' => [
        'driver'                  => env('WHATSAPP_DRIVER', 'mock'),
        'msg91_auth_key'          => env('MSG91_AUTH_KEY'),
        'msg91_integrated_number' => env('MSG91_INTEGRATED_NUMBER'),
        'msg91_template_id'       => env('MSG91_TEMPLATE_ID'),
    ],

    // WhatsApp credit enforcement
    // false = track credits but never block sends (safe default until credits pre-loaded)
    // true  = block sends when balance = 0; throws InsufficientWhatsAppCreditsException
    'whatsapp_credits' => [
        'enforcement' => env('WHATSAPP_CREDIT_ENFORCEMENT', false),
    ],

    // ── Customer Portal OTP ─────────────────────────────────────
    'otp' => [
        'rate_limit_max'     => (int) env('OTP_RATE_LIMIT_MAX', 3),
        'rate_limit_minutes' => (int) env('OTP_RATE_LIMIT_MINUTES', 10),
    ],

];
