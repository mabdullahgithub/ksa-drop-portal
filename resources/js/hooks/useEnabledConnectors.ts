import { useState, useEffect } from 'react'
import type { Connector } from './useConnectors'

export { type Connector }

export function useEnabledConnectors() {
  const [connectors, setConnectors] = useState<Connector[]>([])
  const [comingSoon, setComingSoon] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetch('/api/connectors/enabled', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((data) => {
        setConnectors(data.connectors || [])
        setComingSoon(data.coming_soon || false)
      })
      .finally(() => setLoading(false))
  }, [])

  return { connectors, comingSoon, loading }
}
