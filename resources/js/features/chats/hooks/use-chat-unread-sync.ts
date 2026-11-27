import { useEffect } from 'react'
import { router, usePage } from '@inertiajs/react'
import { getEcho } from '@/lib/echo'
import type { PageProps } from '@/types'

/**
 * Keeps the number beside "Live chat" / "Support" in the sidebar current on
 * every page: when the person's inbox channel reports a change, the `chat`
 * prop is reloaded on its own (a partial reload, nothing else is fetched).
 * Without a live connection the number refreshes on each visit instead.
 */
export function useChatUnreadSync() {
  const { auth, chat } = usePage<PageProps>().props
  const userId = auth?.user?.id
  // Viewing the portal as a client: the number is the client's, not ours.
  const impersonating = !!auth?.impersonating
  const echo = getEcho(!!chat?.realtime)

  useEffect(() => {
    if (!echo || !userId || impersonating || chat === null || chat === undefined) return

    let timer: ReturnType<typeof setTimeout> | null = null
    const reload = () => {
      if (timer) clearTimeout(timer)
      timer = setTimeout(() => router.reload({ only: ['chat'] }), 1200)
    }

    const channel = echo.private(`chat.user.${userId}`)
    channel.listen('.inbox.updated', reload)
    channel.listen('.inbox.read', reload)
    channel.listen('.inbox.removed', reload)

    return () => {
      if (timer) clearTimeout(timer)
      channel.stopListening('.inbox.updated', reload)
      channel.stopListening('.inbox.read', reload)
      channel.stopListening('.inbox.removed', reload)
    }
    // `chat` changes on each reload; only whether it exists matters here.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [echo, userId, impersonating, chat == null])
}
