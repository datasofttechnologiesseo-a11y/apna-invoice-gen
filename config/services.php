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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    // Google OAuth (Sign up / Sign in with Google).
    // Create credentials at https://console.cloud.google.com → APIs & Services
    // → Credentials → OAuth client ID (type: Web application). Add the redirect
    // URI shown below. The "Sign up with Google" button only appears once
    // GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set.
    // Google tag (gtag.js) on every public and signed-in page: GA4 analytics
    // and Google Ads. Loaded on production only unless GOOGLE_TAGS_ENABLED is
    // set. GOOGLE_ADS_SIGNUP_LABEL is the label of the "Sign up" conversion
    // action in Google Ads (Goals > Conversions > the action > Tag setup:
    // send_to 'AW-…/LABEL'); without it, import the GA4 sign_up event instead.
    'google_tags' => [
        'enabled' => (bool) env('GOOGLE_TAGS_ENABLED', env('APP_ENV') === 'production'),
        'ga4' => env('GA4_MEASUREMENT_ID', 'G-8RLQRM0KMV'),
        'ads' => env('GOOGLE_ADS_ID', 'AW-18551697749'),
        'ads_signup_label' => env('GOOGLE_ADS_SIGNUP_LABEL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
