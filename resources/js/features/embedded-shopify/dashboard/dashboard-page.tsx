import { Suspense, lazy, useEffect, useState } from "react";
import {
    embeddedFetch,
    fetchConnectionState,
    EmbeddedApiError,
    type ConnectionState,
    type DashboardData,
} from "../api-client";
import { BOOTSTRAP_DASHBOARD, LINKED_HINT } from "../bootstrap";
import { StoreNotConnected } from "../store-not-connected";
import { DashboardSkeleton } from "./dashboard-skeleton";

/**
 * recharts is ~335 KB — an order of magnitude more than the rest of this app,
 * and nothing above the fold needs it. Loading it lazily keeps it off the
 * critical path; the Suspense fallback reserves the chart's exact height so
 * its arrival shifts nothing.
 */
const OrdersChart = lazy(() =>
    import("./orders-chart").then((m) => ({ default: m.OrdersChart })),
);

const SYNC_STATUS_BADGES: Record<string, { label: string; tone: string }> = {
    processed: { label: "Processed", tone: "success" },
    approved: { label: "Approved", tone: "success" },
    pending_review: { label: "Pending review", tone: "caution" },
    skipped_filtered: { label: "Skipped by filter", tone: "warning" },
    dismissed: { label: "Dismissed", tone: "neutral" },
};

function syncBadge(status: string | null) {
    return (
        SYNC_STATUS_BADGES[status ?? "processed"] ?? {
            label: status ?? "—",
            tone: "neutral",
        }
    );
}

export function DashboardPage() {
    // The shell inlines this store's payload when it can prove who's asking,
    // so the common case renders real data on the very first paint.
    const [data, setData] = useState<DashboardData | null>(BOOTSTRAP_DASHBOARD);
    const [connection, setConnection] = useState<ConnectionState | null>(null);
    const [error, setError] = useState<Error | null>(null);

    useEffect(() => {
        // /claim-token runs on every load, unchanged: under managed installation
        // it is where installation actually completes (token exchange + webhook
        // registration), not merely where link-state comes from. What changed is
        // that it no longer *gates* the dashboard request.
        const state = fetchConnectionState();

        // When the shell says this store is linked, start the dashboard fetch
        // now instead of after link-state returns. Settled into a value rather
        // than left as a rejecting promise so that a disagreement with the hint
        // can't surface as an unhandled rejection.
        const eager =
            BOOTSTRAP_DASHBOARD === null && LINKED_HINT
                ? embeddedFetch<DashboardData>("/dashboard").then(
                      (value) => ({ ok: true, value }) as const,
                      (reason: Error) => ({ ok: false, reason }) as const,
                  )
                : null;

        state
            .then(async (resolved) => {
                setConnection(resolved);

                // Unlinked stores never touch the client-gated endpoint, and a
                // payload from the shell needs no refetch.
                if (!resolved.linked || BOOTSTRAP_DASHBOARD !== null) return;

                const settled = eager ? await eager : null;

                if (settled === null) {
                    setData(await embeddedFetch<DashboardData>("/dashboard"));
                    return;
                }

                if (!settled.ok) throw settled.reason;

                setData(settled.value);
            })
            .catch((e: Error) => setError(e));
    }, []);

    if (connection && !connection.linked) {
        return (
            <StoreNotConnected
                heading="Order Sync"
                shop={connection.shop}
                claimToken={connection.token ?? undefined}
                installed={connection.installed}
            />
        );
    }

    // Only a failure with nothing to show replaces the page. If the shell
    // already handed us this store's data, a failed follow-up request is worth
    // flagging but not worth throwing away a correct dashboard for.
    if (error && !data) {
        if (error instanceof EmbeddedApiError && error.status === 401) {
            return <StoreNotConnected heading="Order Sync" />;
        }
        return (
            <s-page heading="Order Sync">
                <s-banner tone="critical" heading="Could not load dashboard">
                    {error.message}
                </s-banner>
            </s-page>
        );
    }

    if (!data) {
        return <DashboardSkeleton />;
    }

    const { stats } = data;

    return (
        <s-page
            heading="Order Sync"
            subheading={
                data.last_synced_at
                    ? `Last synced ${new Date(data.last_synced_at).toLocaleString()}`
                    : "Not synced yet"
            }
        >
            {error && (
                <s-banner tone="warning" heading="Some data may be out of date">
                    {error.message}
                </s-banner>
            )}

            <s-section heading="Overview">
                <s-grid gridTemplateColumns="repeat(4, 1fr)" gap="base">
                    <StatCard label="Total orders synced" value={stats.total} />
                    <StatCard label="Processed" value={stats.processed} />
                    <StatCard
                        label="Pending review"
                        value={stats.pending_review}
                    />
                    <StatCard label="Skipped by filter" value={stats.skipped} />
                </s-grid>
            </s-section>

            <s-section heading="Orders per day (last 30 days)">
                <Suspense fallback={<div className="eb-skeleton-chart" />}>
                    <OrdersChart data={data.daily_orders} />
                </Suspense>
            </s-section>

            <s-section heading="Recent orders">
                {data.recent_orders.length === 0 ? (
                    <s-paragraph>No orders synced yet.</s-paragraph>
                ) : (
                    <s-table>
                        <s-table-header-row>
                            <s-table-header>Order</s-table-header>
                            <s-table-header>Customer</s-table-header>
                            <s-table-header>Payment</s-table-header>
                            <s-table-header>Status</s-table-header>
                            <s-table-header>Sync</s-table-header>
                            <s-table-header>Total</s-table-header>
                        </s-table-header-row>
                        <s-table-body>
                            {data.recent_orders.map((order) => {
                                const badge = syncBadge(
                                    order.shopify_sync_status,
                                );
                                return (
                                    <s-table-row key={order.id}>
                                        <s-table-cell>
                                            {order.order_number}
                                        </s-table-cell>
                                        <s-table-cell>
                                            {order.customer_name || "—"}
                                        </s-table-cell>
                                        <s-table-cell>
                                            {order.payment_method === "cod"
                                                ? "COD"
                                                : order.payment_method ===
                                                    "prepaid"
                                                  ? "Prepaid"
                                                  : "—"}
                                        </s-table-cell>
                                        <s-table-cell>
                                            {order.financial_status || "—"} /{" "}
                                            {order.fulfillment_status || "—"}
                                        </s-table-cell>
                                        <s-table-cell>
                                            <s-badge tone={badge.tone}>
                                                {badge.label}
                                            </s-badge>
                                        </s-table-cell>
                                        <s-table-cell>
                                            {order.currency} {order.total}
                                        </s-table-cell>
                                    </s-table-row>
                                );
                            })}
                        </s-table-body>
                    </s-table>
                )}
            </s-section>
        </s-page>
    );
}

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <s-box padding="base" borderWidth="base" borderRadius="base">
            <s-stack gap="small-300">
                <s-text tone="subdued">{label}</s-text>
                <s-heading>{value.toLocaleString()}</s-heading>
            </s-stack>
        </s-box>
    );
}
