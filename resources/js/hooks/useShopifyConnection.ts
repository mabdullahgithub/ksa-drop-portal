import { useState, useCallback } from 'react'

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

type SyncMode = 'auto_sync' | 'manual_approval'

/**
 * Mutations for the client's Shopify connection. Read state comes from
 * useEnabledConnectors (the connector card), so this hook is write-only.
 */
export function useShopifyConnection() {
  const [disconnecting, setDisconnecting] = useState(false)
  const [updatingSyncMode, setUpdatingSyncMode] = useState(false)

  const disconnect = useCallback(async (): Promise<boolean> => {
    setDisconnecting(true)
    try {
      const res = await fetch('/portal/api/shopify/disconnect', {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
      })
      return res.ok
    } finally {
      setDisconnecting(false)
    }
  }, [])

  const updateSyncMode = useCallback(async (mode: SyncMode): Promise<boolean> => {
    setUpdatingSyncMode(true)
    try {
      const res = await fetch('/portal/api/shopify/sync-mode', {
        method: 'PUT',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ sync_mode: mode }),
      })
      return res.ok
    } finally {
      setUpdatingSyncMode(false)
    }
  }, [])

  return { disconnect, disconnecting, updateSyncMode, updatingSyncMode }
}
