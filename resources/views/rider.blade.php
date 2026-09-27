<!DOCTYPE html>
<html lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>KSA Express Rider</title>

        {{-- Installable web app. The manifest carries the activation link as
             start_url when the page is one, so an installed app on iPhone
             (own cookie jar, separate from Safari) can use it on first launch. --}}
        {{-- use-credentials: fetched with cookies like the page, so anything
             in front of the app that gates on a cookie lets it through. --}}
        <link rel="manifest" href="{{ $manifestUrl }}" crossorigin="use-credentials">
        {{-- Status bar blends into the glass canvas (resources/css/rider.css). --}}
        <meta name="theme-color" content="#f2f2f7" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b0b0c" media="(prefers-color-scheme: dark)">
        <link rel="icon" type="image/png" href="/rider-icons/icon-192.png">
        <link rel="apple-touch-icon" href="/rider-icons/apple-touch-icon.png">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="KSA Rider">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="format-detection" content="telephone=no">
        <meta name="robots" content="noindex, nofollow">

        {{-- Follow the phone's light/dark setting before first paint. --}}
        <script>
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <script>
            window.__RIDER__ = @json($boot);
        </script>

        @viteReactRefresh
        @vite(['resources/js/rider-app.tsx'])
    </head>
    <body class="antialiased">
        <div id="rider-app"></div>
    </body>
</html>
