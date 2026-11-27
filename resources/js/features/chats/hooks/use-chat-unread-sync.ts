import { useEffect } from 'react'
import { usePage } from '@inertiajs/react'
import axios from 'axios'
import { create } from 'zustand'
import { getEcho } from '@/lib/echo'
import type { PageProps } from '@/types'

/** The number beside "Live chat" / "Support" in the sidebar. */
export const useChatUnread = create<{ count: number; set: (count: number) => void }>((set) => ({
  count: 0,
  set: (count) => set({ count }),
}))

/** However many messages arrive together, the number is asked for once in this long. */
const EVERY_MS = 5000

/**
 * Keeps that number current. Every page visit brings it as a prop; in
 * between, the person's inbox channel says when it may have changed and one
 * small request fetches it. The page itself is never reloaded for it.
 */
export function useChatUnreadSync() {
  const { auth, chat } = usePage<PageProps>().props
  const userId = auth?.user?.id
  // Viewing the portal as a client: the number is the client's, not ours.
  const impersonating = !!auth?.impersonating
  const client = !!auth?.roles?.includes('client')
  const echo = getEcho(!!chat?.realtime)
  const setCount = useChatUnread((state) => state.set)

  useEffect(() => {
    if (chat) setCount(chat.unread)
  }, [chat, setCount])

  const enabled = !!chat

  useEffect(() => {
    if (!echo || !userId || impersonating || !enabled) return

    let timer: ReturnType<typeof setTimeout> | null = null
    const refresh = () => {
      if (timer) return
      timer = setTimeout(() => {
        timer = null
        axios
          .get<{ unread: number }>(client ? '/portal/api/chats/unread' : '/api/chats/unread')
          .then(({ data }) => setCount(data.unread))
          .catch(() => {})
      }, EVERY_MS)
    }

    const channel = echo.private(`chat.user.${userId}`)
    channel.listen('.inbox.updated', refresh)
    channel.listen('.inbox.read', refresh)

    return () => {
      if (timer) clearTimeout(timer)
      channel.stopListening('.inbox.updated', refresh)
      channel.stopListening('.inbox.read', refresh)
    }
  }, [echo, userId, impersonating, client, enabled, setCount])
}
