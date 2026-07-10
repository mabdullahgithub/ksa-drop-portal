<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        {{-- Shopify App Bridge + Polaris web components (must load before our bundle) --}}
        <meta name="shopify-api-key" content="{{ config('services.shopify.key') }}">
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
        <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>

        @viteReactRefresh
        @vite(['resources/js/embedded-app.tsx'])
    </head>
    <body>
        <div id="embedded-root" data-shop="{{ $shop }}" data-host="{{ $host }}"></div>
    </body>
</html>
