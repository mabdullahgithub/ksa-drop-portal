<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="/ksa-al-logo-files/App Branding/Launcher Icons-03.png">

        <!-- Theme Script - Prevent Flash of Unstyled Content (must be first) -->
        <script>
            (function() {
                function getCookie(name) {
                    const value = `; ${document.cookie}`;
                    const parts = value.split(`; ${name}=`);
                    if (parts.length === 2) return parts.pop().split(';').shift();
                }

                const theme = getCookie('vite-ui-theme') || 'system';
                let resolvedTheme = theme;

                if (theme === 'system') {
                    resolvedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }

                document.documentElement.classList.add(resolvedTheme);

                // Preload the sidebar/auth logo for the active theme so it is not
                // discovered only after the JS bundle boots (LCP candidate).
                var logo = document.createElement('link');
                logo.rel = 'preload';
                logo.as = 'image';
                logo.href = '/ksa-al-logo-files/Logo/ksa-drop-logo-' + (resolvedTheme === 'dark' ? 'dark' : 'light') + '.webp';
                logo.setAttribute('fetchpriority', 'high');
                document.head.appendChild(logo);

                // Apply background color immediately to prevent flicker
                if (resolvedTheme === 'dark') {
                    document.documentElement.style.backgroundColor = 'oklch(0.129 0.042 264.695)';
                    document.documentElement.style.color = 'oklch(0.984 0.003 247.858)';
                } else {
                    document.documentElement.style.backgroundColor = 'oklch(1 0 0)';
                    document.documentElement.style.color = 'oklch(0.129 0.042 264.695)';
                }
            })();
        </script>
        <style>
            /* Prevent any white flash during initial load */
            html, body {
                background-color: oklch(1 0 0);
                color: oklch(0.129 0.042 264.695);
            }
            html.dark, html.dark body {
                background-color: oklch(0.129 0.042 264.695) !important;
                color: oklch(0.984 0.003 247.858) !important;
            }
        </style>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <!-- Non-render-blocking: loads as print, swaps to all on load (manrope is unused; inter 300 dropped) -->
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" /></noscript>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
