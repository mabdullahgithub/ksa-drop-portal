import { useState, useEffect } from 'react'

export interface Connector {
  id: number
  key: string
  name: string
  description: string | null
  enabled: boolean
  // Shopify client-specific fields — present only when a client views
  // /api/connectors/enabled (attached server-side from their connection).
  client_connected?: boolean
  shop_domain?: string | null
  sync_mode?: 'auto_sync' | 'manual_approval' | null
  last_synced_at?: string | null
  connected_at?: string | null
  needs_reconnect?: boolean
  webhooks_registered?: boolean
  pending_count?: number
  /** Orders Shopify sent that never synced, and are still unresolved. */
  failed_count?: number
  // Reopens the app inside Shopify Admin — used for both first-time claim
  // discovery and reconnect, since OAuth only ever starts on a Shopify-owned
  // surface, never from a domain typed into the portal.
  admin_app_url?: string | null
  app_store_url?: string | null
}

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

export function useConnectors() {
  const [connectors, setConnectors] = useState<Connector[]>([])
  const [loading, setLoading] = useState(true)
  const [toggling, setToggling] = useState<number | null>(null)

  useEffect(() => {
    fetch('/api/connectors', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then(setConnectors)
      .finally(() => setLoading(false))
  }, [])

  const toggle = async (connector: Connector) => {
    setToggling(connector.id)
    try {
      const res = await fetch(`/api/connectors/${connector.id}/toggle`, {
        method: 'PATCH',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
      })
      if (res.ok) {
        const updated: Connector = await res.json()
        setConnectors((prev) => prev.map((c) => (c.id === updated.id ? updated : c)))
      }
    } finally {
      setToggling(null)
    }
  }

  return { connectors, loading, toggling, toggle }
}
