import { StrictMode, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { DashboardPage } from '@/features/embedded-shopify/dashboard/dashboard-page'
import { SettingsPage } from '@/features/embedded-shopify/settings/settings-page'

/**
 * Embedded Shopify Admin app — a separate React tree from the portal SPA.
 * Rendered inside the Shopify Admin iframe; UI is Polaris web components
 * (s-* custom elements loaded from Shopify's CDN in embedded.blade.php).
 */
function EmbeddedApp() {
    const [view, setView] = useState<'dashboard' | 'settings'>('dashboard')

    return (
        <>
            <s-box padding="base">
                <s-stack direction="inline" gap="small-200">
                    <s-button
                        variant={view === 'dashboard' ? 'primary' : 'secondary'}
                        onClick={() => setView('dashboard')}
                    >
                        Dashboard
                    </s-button>
                    <s-button
                        variant={view === 'settings' ? 'primary' : 'secondary'}
                        onClick={() => setView('settings')}
                    >
                        Settings
                    </s-button>
                </s-stack>
            </s-box>

            {view === 'dashboard' ? <DashboardPage /> : <SettingsPage />}
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
