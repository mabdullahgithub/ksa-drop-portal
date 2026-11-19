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
  const [claiming, setClaiming] = useState(false)

  // Links an already-installed (unlinked) Shopify connection to this client.
  // Never touches Shopify's OAuth endpoint — the token was already obtained
  // when the merchant installed the app from Shopify itself. claimToken is
  // the signed token minted inside the embedded app (see EmbeddedAppController
  // ::claimToken) — it proves the deep link really came from that shop's
  // Shopify Admin session, so the backend isn't just trusting a raw domain
  // string typed into the URL.
  const claimStore = useCallback(
    async (shop: string, claimToken: string): Promise<{ ok: boolean; message?: string }> => {
      setClaiming(true)
      try {
        const res = await fetch('/portal/api/shopify/claim', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
          },
          body: JSON.stringify({ shop, claim_token: claimToken }),
        })
        const data = await res.json().catch(() => ({}))
        return { ok: res.ok, message: data?.message }
      } finally {
        setClaiming(false)
      }
    },
    []
  )

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

  const [retrying, setRetrying] = useState(false)

  const retryWebhooks = useCallback(async (): Promise<{ ok: boolean; message?: string }> => {
    setRetrying(true)
    try {
      const res = await fetch('/portal/api/shopify/retry-webhooks', {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
      })
      const data = await res.json().catch(() => ({}))
      return { ok: res.ok, message: data?.message }
    } finally {
      setRetrying(false)
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

  return {
    disconnect,
    disconnecting,
    updateSyncMode,
    updatingSyncMode,
    retryWebhooks,
    retrying,
    claimStore,
    claiming,
  }
}
