/**
 * Error thrown by embeddedFetch, carrying the HTTP status so callers can
 * distinguish cases — notably 401, which means the store isn't linked to a
 * KSA Drop account yet ("Store is not connected").
 */
export class EmbeddedApiError extends Error {
    status: number

    constructor(message: string, status: number) {
        super(message)
        this.name = 'EmbeddedApiError'
        this.status = status
    }
}

/**
 * Fetch wrapper for the embedded app's API. Every request carries a fresh
 * App Bridge session token (short-lived JWT) as a Bearer header — there is
 * no Laravel session/CSRF inside the Shopify Admin iframe.
 */
export async function embeddedFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
    const token = await window.shopify.idToken()

    const response = await fetch(`/embedded/shopify/api${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
            ...options.headers,
        },
    })

    if (!response.ok) {
        const body = await response.json().catch(() => null)
        throw new EmbeddedApiError(
            body?.message || `Request failed (${response.status})`,
            response.status
        )
    }

    return response.json()
}

export interface DashboardData {
    shop_domain: string
    last_synced_at: string | null
    stats: {
        processed: number
        pending_review: number
        skipped: number
        dismissed: number
        total: number
    }
    daily_orders: DailyOrders[]
    recent_orders: RecentOrder[]
}

export interface DailyOrders {
    date: string
    orders: number
}

export interface RecentOrder {
    id: number
    order_number: string
    customer_name: string | null
    financial_status: string | null
    fulfillment_status: string | null
    payment_method: string | null
    shopify_sync_status: string | null
    currency: string
    total: string
    created_at: string
}

export interface SyncFilters {
    financial_statuses: string[]
    fulfillment_statuses: string[]
    tags_include: string[]
    tags_exclude: string[]
    payment_method: 'all' | 'cod' | 'prepaid'
}

export interface SettingsData {
    sync_mode: 'auto_sync' | 'manual_approval'
    sync_filters: SyncFilters
}
