<!DOCTYPE html>
<html lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>KSA Express Rider</title>

        {{-- The screens repaint these to match a full-colour screen (lib/status-bar.ts). --}}
        <meta name="theme-color" content="#f2f2f7" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b0b0c" media="(prefers-color-scheme: dark)">
        <meta name="format-detection" content="telephone=no">

        {{-- Follow the phone's light/dark setting before first paint. --}}
        <script>
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <script>
            window.__RIDER_NATIVE__ = @json($boot);
        </script>

        <style>
            /* Shown until the app knows who is signed in (resources/js/native-app.tsx). */
            .boot { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: #f2f2f7; }
            .dark .boot { background: #0b0b0c; }
            .boot img { width: 80px; height: 80px; border-radius: 20px; }
        </style>

        @viteReactRefresh
        @vite(['resources/js/native-app.tsx'])
    </head>
    <body class="antialiased">
        <div id="rider-app">
            <div class="boot"><img src="{{ asset('rider-icons/icon-192.png') }}" alt=""></div>
        </div>
    </body>
</html>
