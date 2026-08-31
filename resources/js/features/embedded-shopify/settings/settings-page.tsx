import { useEffect, useRef, useState } from "react";
import {
    embeddedFetch,
    fetchConnectionState,
    EmbeddedApiError,
    type ConnectionState,
    type SettingsData,
    type SyncFilters,
} from "../api-client";
import { BOOTSTRAP_SETTINGS, LINKED_HINT } from "../bootstrap";
import { StoreNotConnected } from "../store-not-connected";
import { SettingsSkeleton } from "./settings-skeleton";
import { StatusFilterCard } from "./status-filter-card";
import { TagFilterCard } from "./tag-filter-card";
import { PaymentMethodCard } from "./payment-method-card";

const DEFAULT_FILTERS: SyncFilters = {
    financial_statuses: [],
    fulfillment_statuses: [],
    tags_include: [],
    tags_exclude: [],
    payment_method: "all",
};

const SAVE_BAR_ID = "sync-settings-save-bar";

/**
 * show/hide reject when the <ui-save-bar> element isn't in the DOM — it only
 * exists in the connected view, not while loading, on the onboarding screen,
 * or once the page has unmounted. Swallow that: an absent save bar has nothing
 * to hide, and the rejection otherwise surfaces as an uncaught promise error
 * in the Shopify Admin console.
 */
function toggleSaveBar(action: "show" | "hide") {
    Promise.resolve(window.shopify.saveBar[action](SAVE_BAR_ID)).catch(
        () => {},
    );
}

export function SettingsPage() {
    // The shell inlines this store's settings when it can prove who's asking,
    // so the common case renders the real form on the very first paint.
    const initial = BOOTSTRAP_SETTINGS?.sync_filters ?? DEFAULT_FILTERS;

    const [filters, setFilters] = useState<SyncFilters>(initial);
    // Last-saved snapshot; dirty-state (and the native save bar) is derived from it.
    const [saved, setSaved] = useState<SyncFilters>(initial);
    const [loading, setLoading] = useState(BOOTSTRAP_SETTINGS === null);
    const [saving, setSaving] = useState(false);
    const [connection, setConnection] = useState<ConnectionState | null>(null);
    const [error, setError] = useState<Error | null>(null);
    const savingRef = useRef(false);

    const dirty = JSON.stringify(filters) !== JSON.stringify(saved);

    useEffect(() => {
        // /claim-token runs on every load, unchanged: under managed installation
        // it is where installation actually completes, not merely where
        // link-state comes from. What changed is that it no longer *gates* the
        // settings request.
        const state = fetchConnectionState();

        // When the shell says this store is linked, start the settings fetch now
        // instead of after link-state returns. Settled into a value rather than
        // left as a rejecting promise so that a disagreement with the hint can't
        // surface as an unhandled rejection.
        const eager =
            BOOTSTRAP_SETTINGS === null && LINKED_HINT
                ? embeddedFetch<SettingsData>("/settings").then(
                      (value) => ({ ok: true, value }) as const,
                      (reason: Error) => ({ ok: false, reason }) as const,
                  )
                : null;

        state
            .then(async (resolved) => {
                setConnection(resolved);

                // Unlinked stores never touch the client-gated endpoint, and a
                // payload from the shell needs no refetch.
                if (!resolved.linked || BOOTSTRAP_SETTINGS !== null) return;

                const settled = eager ? await eager : null;

                if (settled && !settled.ok) throw settled.reason;

                const data = settled
                    ? settled.value
                    : await embeddedFetch<SettingsData>("/settings");

                setFilters(data.sync_filters);
                setSaved(data.sync_filters);
            })
            .catch((e: Error) => setError(e))
            .finally(() => setLoading(false));
    }, []);

    // The save bar markup only renders once the store is known to be connected.
    const saveBarMounted = !loading && !error && connection?.linked === true;

    // Drive Shopify Admin's native contextual save bar from the dirty state.
    useEffect(() => {
        if (!saveBarMounted) return;

        toggleSaveBar(dirty ? "show" : "hide");
    }, [dirty, saveBarMounted]);

    // Never leave a stale save bar behind when navigating away.
    useEffect(() => () => toggleSaveBar("hide"), []);

    const handleSave = async () => {
        if (savingRef.current) return;
        savingRef.current = true;
        setSaving(true);
        try {
            await embeddedFetch("/settings", {
                method: "PUT",
                body: JSON.stringify(filters),
            });
            setSaved(filters);
            window.shopify.toast.show("Sync settings saved");
        } catch (e) {
            window.shopify.toast.show((e as Error).message, { isError: true });
        } finally {
            savingRef.current = false;
            setSaving(false);
        }
    };

    const handleDiscard = () => {
        setFilters(saved);
    };

    if (loading) {
        return <SettingsSkeleton />;
    }

    if (connection && !connection.linked) {
        return (
            <StoreNotConnected
                heading="Sync Settings"
                shop={connection.shop}
                claimToken={connection.token ?? undefined}
                installed={connection.installed}
            />
        );
    }

    // Only a failure with nothing to show replaces the page. If the shell
    // already handed us this store's settings, a failed follow-up request is
    // worth flagging but not worth throwing away a working form for.
    if (error && BOOTSTRAP_SETTINGS === null) {
        if (error instanceof EmbeddedApiError && error.status === 401) {
            return <StoreNotConnected heading="Sync Settings" />;
        }
        return (
            <s-page heading="Sync Settings">
                <s-banner tone="critical" heading="Could not load settings">
                    {error.message}
                </s-banner>
            </s-page>
        );
    }

    return (
        <s-page
            heading="Sync Settings"
            subheading="Control which orders are processed by KSA Drop. By default, every order is processed."
        >
            <ui-save-bar id={SAVE_BAR_ID}>
                <button
                    {...{ variant: "primary" }}
                    onClick={handleSave}
                    disabled={saving}
                ></button>
                <button onClick={handleDiscard} disabled={saving}></button>
            </ui-save-bar>

            {error && (
                <s-banner tone="warning" heading="Some data may be out of date">
                    {error.message}
                </s-banner>
            )}

            <s-section>
                <s-banner tone="info">
                    Filters only apply to new orders. Orders already synced keep
                    their current state when filters change.
                </s-banner>
            </s-section>

            <StatusFilterCard filters={filters} onChange={setFilters} />
            <TagFilterCard filters={filters} onChange={setFilters} />
            <PaymentMethodCard filters={filters} onChange={setFilters} />
        </s-page>
    );
}

export interface FilterCardProps {
    filters: SyncFilters;
    onChange: (filters: SyncFilters) => void;
}
