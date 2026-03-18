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

    // ─── SOS-Expat Integration ───
    'sos_expat' => [
        'check_email_url' => env('SOS_EXPAT_CHECK_EMAIL_URL'),
        'api_key' => env('SOS_EXPAT_API_KEY'),
        'webhook_secret' => env('SOS_EXPAT_WEBHOOK_SECRET'),
        'registration_url' => env('SOS_EXPAT_REGISTRATION_URL', 'https://sos-expat.com/chatter/register'),
    ],

    // ─── MailWizz Email Engine Cold ───
    'mailwizz' => [
        'api_url' => env('MAILWIZZ_API_URL'),
        'api_key' => env('MAILWIZZ_API_KEY'),
    ],

];
