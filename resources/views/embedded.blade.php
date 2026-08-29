<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        {{-- Warm the connection to Shopify's CDN before the script tags below are even parsed --}}
        <link rel="preconnect" href="https://cdn.shopify.com">
        <link rel="dns-prefetch" href="https://cdn.shopify.com">

        {{-- Shopify App Bridge + Polaris web components (must load before our bundle) --}}
        <meta name="shopify-api-key" content="{{ config('services.shopify.key') }}">
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
        <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>

        @viteReactRefresh
        @vite(['resources/js/embedded-app.tsx'])
    </head>
    <body>
        <div id="embedded-root" data-shop="{{ $shop }}" data-host="{{ $host }}" data-portal-url="{{ $portalUrl }}" data-portal-login-url="{{ $portalLoginUrl }}"></div>
    </body>
</html>
