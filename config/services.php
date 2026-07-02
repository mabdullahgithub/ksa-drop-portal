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

    /*
    | J&T Express — fallback credentials used only when the matching
    | ConnectorSetting (saved from Apps → J&T Settings) is missing. The DB
    | settings always take precedence; these env values let the integration
    | keep working before anything is configured in the UI.
    */
    'jnt' => [
        // Set to true only during J&T sandbox joint-debugging.
        // J&T's console test calls use their internal test key, not yours,
        // so signature verification will always fail during that step.
        // Remove or set to false before going live.
        'skip_webhook_verification' => env('JNT_SKIP_WEBHOOK_VERIFICATION', false),
    ],

    'jnt_express' => [
        'api_account' => env('JNT_API_ACCOUNT'),
        'private_key' => env('JNT_PRIVATE_KEY'),
        'customer_code' => env('JNT_CUSTOMER_CODE'),
        'customer_password' => env('JNT_CUSTOMER_PASSWORD'),
        'sandbox_uuid' => env('JNT_SANDBOX_UUID'),
        'base_url' => env('JNT_BASE_URL', 'https://openapi.jtjms-sa.com'),
    ],

    /*
    | Shopify Integration — public app credentials (OAuth authorization code
    | grant with expiring offline tokens). Clients connect their own stores;
    | each connection stores its own access/refresh token in
    | client_shopify_connections.
    */
    'shopify' => [
        'key'          => env('SHOPIFY_API_KEY'),
        'secret'       => env('SHOPIFY_API_SECRET'),
        'scopes'       => env('SHOPIFY_SCOPES', 'read_orders'),
        'redirect_uri' => env('SHOPIFY_REDIRECT_URI'),
    ],

];
