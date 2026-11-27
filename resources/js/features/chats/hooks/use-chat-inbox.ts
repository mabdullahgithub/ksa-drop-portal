import { useCallback, useEffect, useRef, useState } from 'react'
import type Echo from 'laravel-echo'
import type { ChatConversation, ChatSide } from '../types'

interface Page<C> {
  data: ChatConversation[]
  next_cursor: string | null
  counts?: C
}

interface Options<C> {
  side: ChatSide
  /** Fetch one page with the current filters; `key` changes when the filters do. */
  load: (cursor?: string) => Promise<Page<C>>
  key: string
  echo: Echo<'reverb'> | null
  live: boolean
  pollMs: number
  userId: number
  /** The conversation on screen: its new messages are read, not unread. */
  openId: number | null
}

type InboxEvent = {
  conversation: ChatConversation
  message?: { id: number; sender_id: number | null; sender_type: string; kind: string; internal: boolean }
}

/**
 * The list of conversations: pages, live updates to one person's inbox
 * channel (or polling), unread numbers, and a quiet re-fetch after a burst
 * of changes so the filters and the tab counts stay exact.
 */
export function useChatInbox<C = undefined>({ side, load, key, echo, live, pollMs, userId, openId }: Options<C>) {
  const [items, setItems] = useState<ChatConversation[]>([])
  const [counts, setCounts] = useState<C | undefined>(undefined)
  const [nextCursor, setNextCursor] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadingMore, setLoadingMore] = useState(false)

  const loadRef = useRef(load)
  loadRef.current = load
  const openRef = useRef(openId)
  openRef.current = openId
  const keyRef = useRef(key)
  keyRef.current = key
  const request = useRef(0)
  /** The `key` the list on screen was loaded for. */
  const [loadedKey, setLoadedKey] = useState<string | null>(null)
  const reconcileTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  const refresh = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true)
    const mine = ++request.current
    const forKey = keyRef.current
    try {
      const page = await loadRef.current()
      // An answer for older filters must not overwrite a newer one.
      if (mine !== request.current) return
      setItems(page.data)
      setNextCursor(page.next_cursor)
      if (page.counts) setCounts(page.counts)
      setLoadedKey(forKey)
      setLoading(false)
    } catch {
      if (mine === request.current && !quiet) {
        setItems([])
        setLoading(false)
      }
    }
  }, [])

  useEffect(() => {
    void refresh()
  }, [key, refresh])

  const more = useCallback(async () => {
    if (!nextCursor || loadingMore) return
    setLoadingMore(true)
    try {
      const page = await loadRef.current(nextCursor)
      setItems((current) => [...current, ...page.data.filter((c) => !current.some((x) => x.id === c.id))])
      setNextCursor(page.next_cursor)
    } finally {
      setLoadingMore(false)
    }
  }, [nextCursor, loadingMore])

  // The list is already updated from the event itself; this only settles the
  // filters and counts, so one request covers everything in the next 5 s.
  const reconcile = useCallback(() => {
    if (reconcileTimer.current) return
    reconcileTimer.current = setTimeout(() => {
      reconcileTimer.current = null
      void refresh(true)
    }, 5000)
  }, [refresh])

  /** Put a conversation's newest state in the list, at the top when it moved. */
  const upsert = useCallback((conversation: ChatConversation, bumpUnread = false) => {
    setItems((current) => {
      const existing = current.find((c) => c.id === conversation.id)
      const unread = conversation.id === openRef.current ? 0 : (existing?.unread_count ?? 0) + (bumpUnread ? 1 : 0)
      const merged = { ...existing, ...conversation, unread_count: unread }
      const rest = current.filter((c) => c.id !== conversation.id)
      return [merged, ...rest].sort(
        (a, b) => new Date(b.last_message_at ?? 0).getTime() - new Date(a.last_message_at ?? 0).getTime() || b.id - a.id
      )
    })
  }, [])

  const markRead = useCallback((id: number) => {
    setItems((current) => current.map((c) => (c.id === id ? { ...c, unread_count: 0 } : c)))
  }, [])

  // Live inbox.
  useEffect(() => {
    if (!echo) return
    const channel = echo.private(`chat.user.${userId}`)

    const onUpdated = (event: InboxEvent) => {
      const m = event.message
      const fromOther = !!m && m.kind !== 'event' && (side === 'team' ? m.sender_id !== userId : m.sender_type === 'staff')
      upsert(event.conversation, fromOther)
      reconcile()
    }
    const onRead = (event: { conversation_id: number }) => markRead(event.conversation_id)

    channel.listen('.inbox.updated', onUpdated)
    channel.listen('.inbox.read', onRead)

    // The channel is shared with the sidebar number: stop listening, never leave.
    return () => {
      channel.stopListening('.inbox.updated', onUpdated)
      channel.stopListening('.inbox.read', onRead)
    }
  }, [echo, userId, side, upsert, markRead, reconcile])

  // Polling while the socket is down.
  useEffect(() => {
    if (live) return
    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') void refresh(true)
    }, pollMs)
    return () => clearInterval(timer)
  }, [live, pollMs, refresh])

  useEffect(() => () => {
    if (reconcileTimer.current) clearTimeout(reconcileTimer.current)
  }, [])

  return { items, counts, loading, loadedKey, nextCursor, loadingMore, more, refresh, upsert, markRead }
}
