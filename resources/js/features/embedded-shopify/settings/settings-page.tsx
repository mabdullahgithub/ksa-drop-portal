import { useEffect, useState } from 'react'
import { embeddedFetch, type SettingsData, type SyncFilters } from '../api-client'
import { StatusFilterCard } from './status-filter-card'
import { TagFilterCard } from './tag-filter-card'
import { PaymentMethodCard } from './payment-method-card'

const DEFAULT_FILTERS: SyncFilters = {
    financial_statuses: [],
    fulfillment_statuses: [],
    tags_include: [],
    tags_exclude: [],
    payment_method: 'all',
}

export function SettingsPage() {
    const [filters, setFilters] = useState<SyncFilters>(DEFAULT_FILTERS)
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState('')

    useEffect(() => {
        embeddedFetch<SettingsData>('/settings')
            .then((data) => setFilters(data.sync_filters))
            .catch((e: Error) => setError(e.message))
            .finally(() => setLoading(false))
    }, [])

    const handleSave = async () => {
        setSaving(true)
        try {
            await embeddedFetch('/settings', {
                method: 'PUT',
                body: JSON.stringify(filters),
            })
            window.shopify.toast.show('Sync settings saved')
        } catch (e) {
            window.shopify.toast.show((e as Error).message, { isError: true })
        } finally {
            setSaving(false)
        }
    }

    if (loading) {
        return (
            <s-page heading="Sync Settings">
                <s-stack alignItems="center" padding="large">
                    <s-spinner accessibilityLabel="Loading settings" />
                </s-stack>
            </s-page>
        )
    }

    if (error) {
        return (
            <s-page heading="Sync Settings">
                <s-banner tone="critical" heading="Could not load settings">
                    {error}
                </s-banner>
            </s-page>
        )
    }

    return (
        <s-page heading="Sync Settings" subheading="Control which orders are processed by KSA Drop. By default, every order is processed.">
            <s-section>
                <s-banner tone="info">
                    Filters only apply to new orders. Orders already synced keep their current
                    state when filters change.
                </s-banner>
            </s-section>

            <StatusFilterCard filters={filters} onChange={setFilters} />
            <TagFilterCard filters={filters} onChange={setFilters} />
            <PaymentMethodCard filters={filters} onChange={setFilters} />

            <s-section>
                <s-button variant="primary" disabled={saving} onClick={handleSave}>
                    {saving ? 'Saving…' : 'Save settings'}
                </s-button>
            </s-section>
        </s-page>
    )
}

export interface FilterCardProps {
    filters: SyncFilters
    onChange: (filters: SyncFilters) => void
}
