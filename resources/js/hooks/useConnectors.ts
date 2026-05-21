import { useState, useEffect } from 'react'

export interface Connector {
  id: number
  key: string
  name: string
  description: string | null
  enabled: boolean
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
