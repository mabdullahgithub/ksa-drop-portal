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
  needs_reconnect?: boolean
  pending_count?: number
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
