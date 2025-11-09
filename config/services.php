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

    'dropbox' => [
        'client_id' => env('DROPBOX_CLIENT_ID'),
        'client_secret' => env('DROPBOX_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/api/dropbox/callback',
        'access_token' => env('DROPBOX_ACCESS_TOKEN'),
        'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),
    ],

    'square' => [
        'access_token' => env('SQUARE_ACCESS_TOKEN'),
        'location_id' => env('SQUARE_LOCATION_ID'),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
        'currency' => env('SQUARE_CURRENCY', 'USD'),
    ],

    'google' => [
        'places_api_key' => env('GOOGLE_PLACES_API_KEY'),
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // LocationIQ (OSM-backed) for address autocomplete/geocoding
    'locationiq' => [
        'key' => env('LOCATIONIQ_API_KEY'),
        'base_url' => env('LOCATIONIQ_BASE_URL', 'https://api.locationiq.com/v1'),
    ],

    // Address provider selector
    'address' => [
        // Supported: locationiq, google
        'provider' => env('ADDRESS_PROVIDER', 'locationiq'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
