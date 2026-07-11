import { useEffect, useState } from 'react'
import { embeddedFetch, EmbeddedApiError, type DashboardData } from '../api-client'
import { StoreNotConnected } from '../store-not-connected'
import { OrdersChart } from './orders-chart'

const SYNC_STATUS_BADGES: Record<string, { label: string; tone: string }> = {
    processed: { label: 'Processed', tone: 'success' },
    approved: { label: 'Approved', tone: 'success' },
    pending_review: { label: 'Pending review', tone: 'caution' },
    skipped_filtered: { label: 'Skipped by filter', tone: 'warning' },
    dismissed: { label: 'Dismissed', tone: 'neutral' },
}

function syncBadge(status: string | null) {
    return SYNC_STATUS_BADGES[status ?? 'processed'] ?? { label: status ?? '—', tone: 'neutral' }
}

export function DashboardPage() {
    const [data, setData] = useState<DashboardData | null>(null)
    const [error, setError] = useState<Error | null>(null)

    useEffect(() => {
        embeddedFetch<DashboardData>('/dashboard')
            .then(setData)
            .catch((e: Error) => setError(e))
    }, [])

    if (error) {
        if (error instanceof EmbeddedApiError && error.status === 401) {
            return <StoreNotConnected heading="Order Sync" />
        }
        return (
            <s-page heading="Order Sync">
                <s-banner tone="critical" heading="Could not load dashboard">
                    {error.message}
                </s-banner>
            </s-page>
        )
    }

    if (!data) {
        return (
            <s-page heading="Order Sync">
                <s-stack alignItems="center" padding="large">
                    <s-spinner accessibilityLabel="Loading dashboard" />
                </s-stack>
            </s-page>
        )
    }

    const { stats } = data

    return (
        <s-page
            heading="Order Sync"
            subheading={
                data.last_synced_at
                    ? `Last synced ${new Date(data.last_synced_at).toLocaleString()}`
                    : 'Not synced yet'
            }
        >
            <s-section heading="Overview">
                <s-grid gridTemplateColumns="repeat(4, 1fr)" gap="base">
                    <StatCard label="Total orders synced" value={stats.total} />
                    <StatCard label="Processed" value={stats.processed} />
                    <StatCard label="Pending review" value={stats.pending_review} />
                    <StatCard label="Skipped by filter" value={stats.skipped} />
                </s-grid>
            </s-section>

            <s-section heading="Orders per day (last 30 days)">
                <OrdersChart data={data.daily_orders} />
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
                                const badge = syncBadge(order.shopify_sync_status)
                                return (
                                    <s-table-row key={order.id}>
                                        <s-table-cell>{order.order_number}</s-table-cell>
                                        <s-table-cell>{order.customer_name || '—'}</s-table-cell>
                                        <s-table-cell>
                                            {order.payment_method === 'cod'
                                                ? 'COD'
                                                : order.payment_method === 'prepaid'
                                                  ? 'Prepaid'
                                                  : '—'}
                                        </s-table-cell>
                                        <s-table-cell>
                                            {order.financial_status || '—'} /{' '}
                                            {order.fulfillment_status || '—'}
                                        </s-table-cell>
                                        <s-table-cell>
                                            <s-badge tone={badge.tone}>{badge.label}</s-badge>
                                        </s-table-cell>
                                        <s-table-cell>
                                            {order.currency} {order.total}
                                        </s-table-cell>
                                    </s-table-row>
                                )
                            })}
                        </s-table-body>
                    </s-table>
                )}
            </s-section>
        </s-page>
    )
}

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <s-box padding="base" borderWidth="base" borderRadius="base">
            <s-stack gap="small-300">
                <s-text tone="subdued">{label}</s-text>
                <s-heading>{value.toLocaleString()}</s-heading>
            </s-stack>
        </s-box>
    )
}
