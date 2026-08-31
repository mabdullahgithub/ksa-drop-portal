import type { DashboardData, SettingsData } from "./api-client";

const root = document.getElementById("embedded-root");

/**
 * Payload inlined by resources/views/embedded.blade.php, resolved server-side
 * from the id_token Shopify puts on the embedded app URL. Present only for a
 * store that is installed and linked, and only for the view being rendered;
 * null otherwise, in which case the app fetches exactly as it always has.
 *
 * Read once at module scope — the shell renders it before our bundle executes.
 */
function readBootstrap(): {
    dashboard?: DashboardData;
    settings?: SettingsData;
} {
    const el = document.getElementById("embedded-bootstrap");

    if (!el?.textContent) return {};

    try {
        return JSON.parse(el.textContent);
    } catch {
        // A malformed payload is not worth failing the page over; fall back to
        // the fetch path, which is the only path this app had before.
        return {};
    }
}

const BOOTSTRAP = readBootstrap();

export const BOOTSTRAP_DASHBOARD = BOOTSTRAP.dashboard ?? null;

export const BOOTSTRAP_SETTINGS = BOOTSTRAP.settings ?? null;

/**
 * Whether the shell believes this store is linked to a KSA Drop client.
 *
 * A *rendering* hint only — it decides whether the client-gated fetch is worth
 * starting immediately rather than after /claim-token comes back. It is never
 * an authorization input: the endpoints authenticate every request off the App
 * Bridge session token regardless, and the UI's linked/unlinked decision still
 * comes from that authenticated response. When the hint is false the app takes
 * the original sequential path, so an unlinked store still never provokes a 401.
 */
export const LINKED_HINT = root?.dataset.linked === "1";
