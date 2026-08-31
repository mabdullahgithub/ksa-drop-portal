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

        {{-- App Bridge must stay synchronous and first: Shopify requires it to
             initialize before anything else touches window.shopify. --}}
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>

        {{-- Polaris only has to be defined before OUR code renders any <s-*>
             element, not before the document body is parsed. Deferring it lets
             the skeleton below paint while it downloads, and ordering still
             holds: deferred classic scripts run before module scripts that come
             after them, so @vite's module still sees the custom elements. --}}
        <script defer src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>

        @viteReactRefresh
        @vite(['resources/js/embedded-app.tsx'])

        {{-- Shared by the static skeleton below and its React counterpart
             (dashboard-skeleton.tsx), so the handoff when React mounts is
             invisible. Deliberately plain CSS on plain elements: this has to
             paint without waiting for the Polaris custom elements to upgrade. --}}
        <style>
            .eb-skeleton {
                --eb-ink: #303030;
                --eb-ink-subdued: #616161;
                --eb-surface: #f3f3f3;
                --eb-border: #e3e3e3;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                max-width: 998px;
                margin: 0 auto;
                padding: 16px;
                color: var(--eb-ink);
            }
            @media (prefers-color-scheme: dark) {
                .eb-skeleton {
                    --eb-ink: #e3e3e3;
                    --eb-ink-subdued: #b5b5b5;
                    --eb-surface: #303030;
                    --eb-border: #4a4a4a;
                }
            }
            .eb-skeleton-title { font-size: 20px; font-weight: 650; margin: 0 0 16px; }
            .eb-skeleton-section { margin-bottom: 24px; }
            .eb-skeleton-heading { font-size: 14px; font-weight: 650; margin: 0 0 8px; }
            .eb-skeleton-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }
            @media (max-width: 720px) {
                .eb-skeleton-grid { grid-template-columns: repeat(2, 1fr); }
            }
            .eb-skeleton-card {
                border: 1px solid var(--eb-border);
                border-radius: 8px;
                padding: 12px;
            }
            .eb-skeleton-label { display: block; font-size: 13px; color: var(--eb-ink-subdued); }
            .eb-skeleton-value { display: block; font-size: 20px; font-weight: 650; margin-top: 4px; }
            /* Matches the 240px .orders-chart height so the real chart swaps in
               without shifting anything below it. */
            .eb-skeleton-chart {
                height: 240px;
                border-radius: 8px;
                background: var(--eb-surface);
            }
            .eb-skeleton-table {
                height: 320px;
                border-radius: 8px;
                background: var(--eb-surface);
            }
            .eb-skeleton-subtitle {
                font-size: 13px;
                color: var(--eb-ink-subdued);
                margin: -8px 0 16px;
            }
            .eb-skeleton-block { height: 180px; }
        </style>
    </head>
    <body>
        @if ($bootstrap !== null)
            {{-- This store's payload for the current view, resolved server-side
                 from the verified id_token. Lets the first paint show real data
                 instead of a loading state. Absent whenever it can't be trusted
                 — the app then fetches as it always has. --}}
            {{-- json_encode directly rather than @json: the flags matter here and
                 @json does not apply them. JSON_HEX_TAG escapes < and > so that
                 order data (customer names, tags) can never close this script
                 tag and break out into markup. --}}
            <script id="embedded-bootstrap" type="application/json">{!! json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif

        {{-- React replaces these children on its first render. The React
             loading state renders the same markup, so there is no blank frame
             between this painting and the app mounting. --}}
        <div id="embedded-root"
             data-shop="{{ $shop }}"
             data-host="{{ $host }}"
             data-portal-url="{{ $portalUrl }}"
             data-portal-login-url="{{ $portalLoginUrl }}"
             data-linked="{{ $linkedHint ? '1' : '0' }}">
            @if ($view === 'settings')
                <div class="eb-skeleton">
                    <h1 class="eb-skeleton-title">Sync Settings</h1>
                    <p class="eb-skeleton-subtitle">Control which orders are processed by KSA Drop. By default, every order is processed.</p>
                    <div class="eb-skeleton-section"><div class="eb-skeleton-card eb-skeleton-block"></div></div>
                    <div class="eb-skeleton-section"><div class="eb-skeleton-card eb-skeleton-block"></div></div>
                    <div class="eb-skeleton-section"><div class="eb-skeleton-card eb-skeleton-block"></div></div>
                </div>
            @else
                <div class="eb-skeleton">
                    <h1 class="eb-skeleton-title">Order Sync</h1>
                    <div class="eb-skeleton-section">
                        <h2 class="eb-skeleton-heading">Overview</h2>
                        <div class="eb-skeleton-grid">
                            <div class="eb-skeleton-card"><span class="eb-skeleton-label">Total orders synced</span><span class="eb-skeleton-value">&mdash;</span></div>
                            <div class="eb-skeleton-card"><span class="eb-skeleton-label">Processed</span><span class="eb-skeleton-value">&mdash;</span></div>
                            <div class="eb-skeleton-card"><span class="eb-skeleton-label">Pending review</span><span class="eb-skeleton-value">&mdash;</span></div>
                            <div class="eb-skeleton-card"><span class="eb-skeleton-label">Skipped by filter</span><span class="eb-skeleton-value">&mdash;</span></div>
                        </div>
                    </div>
                    <div class="eb-skeleton-section">
                        <h2 class="eb-skeleton-heading">Orders per day (last 30 days)</h2>
                        <div class="eb-skeleton-chart"></div>
                    </div>
                    <div class="eb-skeleton-section">
                        <h2 class="eb-skeleton-heading">Recent orders</h2>
                        <div class="eb-skeleton-table"></div>
                    </div>
                </div>
            @endif
            </div>
        </div>
    </body>
</html>
