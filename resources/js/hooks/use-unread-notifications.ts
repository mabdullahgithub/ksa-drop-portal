import { useEffect } from 'react'
import { usePage } from '@inertiajs/react'
import { create } from 'zustand'
import { type PageProps } from '@/types'

interface UnreadState {
  count: number
  // The last count the server shared. Only a new one is adopted, so a
  // component mounting later cannot bring back a number already acted on.
  shared: number | null
  adopt: (shared: number) => void
  setCount: (count: number) => void
  decrement: () => void
}

const useUnreadStore = create<UnreadState>()((set) => ({
  count: 0,
  shared: null,
  adopt: (shared) =>
    set((state) => (state.shared === shared ? state : { count: shared, shared })),
  setCount: (count) => set({ count }),
  decrement: () => set((state) => ({ count: Math.max(0, state.count - 1) })),
}))

/**
 * The unread notifications count, one number for the bell, the sidebar and
 * the notifications page so marking read in one place updates the others.
 *
 * Every Inertia navigation delivers a fresh, server-cached count for free via
 * the shared `unreadNotificationsCount` prop (see HandleInertiaRequests); it
 * is adopted whenever it changes instead of firing a dedicated request.
 */
export function useUnreadNotifications() {
  const shared = usePage<PageProps>().props.unreadNotificationsCount ?? 0
  const { count, shared: adopted, adopt, setCount, decrement } = useUnreadStore()

  useEffect(() => {
    adopt(shared)
  }, [shared, adopt])

  return {
    unreadCount: adopted === null ? shared : count,
    setUnreadCount: setCount,
    decrementUnread: decrement,
  }
}
