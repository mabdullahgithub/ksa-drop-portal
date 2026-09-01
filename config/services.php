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
    | iMile — fallback credentials used only when the matching ConnectorSetting
    | (saved from Apps → iMile Settings) is missing. The DB settings always
    | take precedence; these env values let the integration keep working
    | before anything is configured in the UI.
    */
    'imile' => [
        'customer_id' => env('IMILE_CUSTOMER_ID'),
        'api_key' => env('IMILE_API_KEY'),
        // Production: https://openapi.imile.com — Testing: https://openapi.52imile.cn
        'base_url' => env('IMILE_BASE_URL', 'https://openapi.imile.com'),
        'time_zone' => env('IMILE_TIME_ZONE', '+3'),
    ],

    /*
    | LogesTechs (Navix) — fallback values used only when the matching
    | ConnectorSetting (saved from Apps → LogesTechs Settings) is missing.
    |
    | Unlike J&T and iMile, LogesTechs authenticates every Create/Cancel
    | Shipment call with the plain account email/password in the request body
    | (confirmed with LogesTechs — their token endpoint is not used for
    | server-to-server integrations), so those live in ConnectorSetting
    | (encrypted) rather than here. The webhook username/password are the
    | values configured in LogesTechs' own "Edit customer webhook" panel and
    | echoed back on every push — see LogesTechsWebhookController.
    |
    | The defaults below are the shared Navix test account, so the integration
    | works out of the box during development without any setup.
    |
    | ⚠️ These are NOT sandbox credentials. LogesTechs has no sandbox — company
    | 722 is a live tenant and every shipment created against it dispatches a
    | real driver. Override all five values in production, either via .env or
    | (preferred) via Apps → LogesTechs Settings, which takes precedence.
    */
    'logestechs' => [
        'company_id' => env('LOGESTECHS_COMPANY_ID', '722'),
        'email' => env('LOGESTECHS_EMAIL', 'test@navix.com.sa'),
        'password' => env('LOGESTECHS_PASSWORD', 'test@123'),
        // Matches the values currently set in the Navix account's webhook panel.
        'webhook_username' => env('LOGESTECHS_WEBHOOK_USERNAME', 'test'),
        'webhook_password' => env('LOGESTECHS_WEBHOOK_PASSWORD', 'test'),
        'base_url' => env('LOGESTECHS_BASE_URL', 'https://apisv2.logestechs.com/api'),
        // "Package Source" in LogesTechs' portal.
        'integration_source' => env('LOGESTECHS_INTEGRATION_SOURCE', 'ksadrop_portal'),
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
        'scopes'       => env('SHOPIFY_SCOPES', 'read_orders,read_customers'),
        // Must exactly match an "Allowed redirection URL" in the Partner
        // Dashboard. Defaults to the app's own /shopify/callback route so a
        // missing env var can't produce a broken authorize URL.
        'redirect_uri' => env('SHOPIFY_REDIRECT_URI') ?: rtrim((string) env('APP_URL'), '/') . '/shopify/callback',
        // Public portal URL surfaced by the embedded app so merchants can log
        // in and connect their store. Falls back to the app URL for local dev.
        'portal_url'   => env('KSADROP_PORTAL_URL', env('APP_URL')),
        // App Store listing — linked from the portal for a client who hasn't
        // installed on Shopify yet. Never build an install link from a
        // manually-typed shop domain (App Store review requirement 2.3.1);
        // installation must start on a Shopify-owned surface.
        'app_store_url' => env('SHOPIFY_APP_STORE_URL'),
    ],

];
