import { useEffect, useRef, useState } from 'react'
import {
    embeddedFetch,
    fetchConnectionState,
    EmbeddedApiError,
    type ConnectionState,
    type SettingsData,
    type SyncFilters,
} from '../api-client'
import { StoreNotConnected } from '../store-not-connected'
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

const SAVE_BAR_ID = 'sync-settings-save-bar'

export function SettingsPage() {
    const [filters, setFilters] = useState<SyncFilters>(DEFAULT_FILTERS)
    // Last-saved snapshot; dirty-state (and the native save bar) is derived from it.
    const [saved, setSaved] = useState<SyncFilters>(DEFAULT_FILTERS)
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [connection, setConnection] = useState<ConnectionState | null>(null)
    const [error, setError] = useState<Error | null>(null)
    const savingRef = useRef(false)

    const dirty = JSON.stringify(filters) !== JSON.stringify(saved)

    useEffect(() => {
        // Resolve link-state first so an unlinked store shows onboarding without
        // hitting the client-gated settings endpoint (which would 401).
        fetchConnectionState()
            .then((state) => {
                setConnection(state)
                if (!state.linked) return

                return embeddedFetch<SettingsData>('/settings').then((data) => {
                    setFilters(data.sync_filters)
                    setSaved(data.sync_filters)
                })
            })
            .catch((e: Error) => setError(e))
            .finally(() => setLoading(false))
    }, [])

    // Drive Shopify Admin's native contextual save bar from the dirty state.
    useEffect(() => {
        if (dirty) {
            void window.shopify.saveBar.show(SAVE_BAR_ID)
        } else {
            void window.shopify.saveBar.hide(SAVE_BAR_ID)
        }
    }, [dirty])

    // Never leave a stale save bar behind when navigating away.
    useEffect(() => () => void window.shopify.saveBar.hide(SAVE_BAR_ID), [])

    const handleSave = async () => {
        if (savingRef.current) return
        savingRef.current = true
        setSaving(true)
        try {
            await embeddedFetch('/settings', {
                method: 'PUT',
                body: JSON.stringify(filters),
            })
            setSaved(filters)
            window.shopify.toast.show('Sync settings saved')
        } catch (e) {
            window.shopify.toast.show((e as Error).message, { isError: true })
        } finally {
            savingRef.current = false
            setSaving(false)
        }
    }

    const handleDiscard = () => {
        setFilters(saved)
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

    if (connection && !connection.linked) {
        return (
            <StoreNotConnected
                heading="Sync Settings"
                shop={connection.shop}
                claimToken={connection.token}
            />
        )
    }

    if (error) {
        if (error instanceof EmbeddedApiError && error.status === 401) {
            return <StoreNotConnected heading="Sync Settings" />
        }
        return (
            <s-page heading="Sync Settings">
                <s-banner tone="critical" heading="Could not load settings">
                    {error.message}
                </s-banner>
            </s-page>
        )
    }

    return (
        <s-page heading="Sync Settings" subheading="Control which orders are processed by KSA Drop. By default, every order is processed.">
            <ui-save-bar id={SAVE_BAR_ID}>
                <button {...{ variant: 'primary' }} onClick={handleSave} disabled={saving}></button>
                <button onClick={handleDiscard} disabled={saving}></button>
            </ui-save-bar>

            <s-section>
                <s-banner tone="info">
                    Filters only apply to new orders. Orders already synced keep their current
                    state when filters change.
                </s-banner>
            </s-section>

            <StatusFilterCard filters={filters} onChange={setFilters} />
            <TagFilterCard filters={filters} onChange={setFilters} />
            <PaymentMethodCard filters={filters} onChange={setFilters} />
        </s-page>
    )
}

export interface FilterCardProps {
    filters: SyncFilters
    onChange: (filters: SyncFilters) => void
}
