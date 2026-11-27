import { useEffect, useRef } from 'react'
import type { ChatConversation } from '../types'

/**
 * Keeps the thread and the list telling the same story:
 *  - the person changes a filter or the search, and the open chat isn't in the
 *    result: the thread closes;
 *  - the open chat leaves the list any other way (it was just resolved, or it
 *    was opened from a notification): it is returned, to be listed on its own
 *    at the top, so what is on screen is always selected in the list.
 */
export function useOpenChatInList({
  items,
  loading,
  loadedKey,
  selectedId,
  open,
  onFilteredOut,
}: {
  items: ChatConversation[]
  loading: boolean
  loadedKey: string | null
  selectedId: number | null
  /** The open chat's details, once loaded. */
  open: ChatConversation | undefined
  onFilteredOut: () => void
}): ChatConversation | null {
  const previousKey = useRef<string | null>(null)

  useEffect(() => {
    if (loadedKey === null || loadedKey === previousKey.current) return
    const firstLoad = previousKey.current === null
    previousKey.current = loadedKey

    if (!firstLoad && selectedId && !items.some((c) => c.id === selectedId)) onFilteredOut()
    // Only a new set of filters closes the thread, not the list changing under it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loadedKey])

  if (loading || !open || open.id !== selectedId || items.some((c) => c.id === open.id)) return null

  return { ...open, unread_count: 0 }
}
