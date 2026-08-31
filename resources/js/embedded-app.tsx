import { StrictMode, Suspense, lazy } from "react";
import { createRoot } from "react-dom/client";
import { DashboardPage } from "@/features/embedded-shopify/dashboard/dashboard-page";
import { SettingsSkeleton } from "@/features/embedded-shopify/settings/settings-skeleton";

/**
 * Embedded Shopify Admin app — a separate React tree from the portal SPA.
 * Rendered inside the Shopify Admin iframe; UI is Polaris web components
 * (s-* custom elements loaded from Shopify's CDN in embedded.blade.php).
 *
 * Navigation lives in the Shopify Admin sidebar via App Bridge's
 * <ui-nav-menu>: each item is a real URL served by the same Blade shell,
 * and the view is picked off the pathname.
 *
 * Navigation being real page loads means a visit only ever needs one of these
 * two trees, so Settings is split out rather than shipped to every dashboard
 * visitor. The dashboard itself stays in the entry deliberately: it is the home
 * route, and at ~4 KB it is not worth paying a second round trip for. The heavy
 * dependency it used to drag along — recharts — is the thing that got split,
 * inside dashboard-page.tsx.
 */
function EmbeddedApp() {
    const isSettings = window.location.pathname.endsWith("/settings");

    return (
        <>
            <ui-nav-menu>
                <a href="/embedded/shopify" rel="home">
                    Dashboard
                </a>
                <a href="/embedded/shopify/settings">Settings</a>
            </ui-nav-menu>

            {isSettings ? (
                <Suspense fallback={<SettingsSkeleton />}>
                    <SettingsPage />
                </Suspense>
            ) : (
                <DashboardPage />
            )}
        </>
    );
}

const SettingsPage = lazy(() =>
    import("@/features/embedded-shopify/settings/settings-page").then((m) => ({
        default: m.SettingsPage,
    })),
);

const container = document.getElementById("embedded-root");

if (container) {
    createRoot(container).render(
        <StrictMode>
            <EmbeddedApp />
        </StrictMode>,
    );
}
