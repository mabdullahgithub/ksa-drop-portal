import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { DashboardPage } from '@/features/embedded-shopify/dashboard/dashboard-page'
import { SettingsPage } from '@/features/embedded-shopify/settings/settings-page'

/**
 * Embedded Shopify Admin app — a separate React tree from the portal SPA.
 * Rendered inside the Shopify Admin iframe; UI is Polaris web components
 * (s-* custom elements loaded from Shopify's CDN in embedded.blade.php).
 *
 * Navigation lives in the Shopify Admin sidebar via App Bridge's
 * <ui-nav-menu>: each item is a real URL served by the same Blade shell,
 * and the view is picked off the pathname.
 */
function EmbeddedApp() {
    const isSettings = window.location.pathname.endsWith('/settings')

    return (
        <>
            <ui-nav-menu>
                <a href="/embedded/shopify" rel="home">
                    Dashboard
                </a>
                <a href="/embedded/shopify/settings">Settings</a>
            </ui-nav-menu>

            {isSettings ? <SettingsPage /> : <DashboardPage />}
        </>
    )
}

const container = document.getElementById('embedded-root')

if (container) {
    createRoot(container).render(
        <StrictMode>
            <EmbeddedApp />
        </StrictMode>
    )
}
