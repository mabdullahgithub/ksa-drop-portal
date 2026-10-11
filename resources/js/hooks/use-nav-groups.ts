import { useMemo } from 'react'
import { usePage } from '@inertiajs/react'
import { Lock } from 'lucide-react'
import { sidebarData } from '@/components/layout/data/sidebar-data'
import type { NavGroup as NavGroupType } from '@/components/layout/types'
import { useUnreadNotifications } from '@/hooks/use-unread-notifications'

const portalFeatureUrlMap: Record<string, string> = {
  orders: '/portal/orders',
  inventory: '/portal/inventory',
  products: '/portal/products',
  revenue: '/portal/revenue',
  finance: '/portal/finance',
}

const LOCKED_GROUP_TITLE = 'Under Development'

/**
 * Resolves the nav groups for the current user, applying portal-feature,
 * impersonation and dropshipper rules. Permission/role filtering is still
 * handled per-item by the consumer (NavGroup / command menu).
 *
 * Shared by the sidebar and the command menu so both stay in sync.
 */
export function useNavGroups(): NavGroupType[] {
  const { auth, pendingRequestsCount, locks } = usePage().props as any
  const { unreadCount } = useUnreadNotifications()
  const portalFeatures: string[] | null = auth?.portal_features
  const isImpersonating: boolean = !!auth?.impersonating
  const isDropshipper: boolean = !!auth?.client?.is_dropshipper

  return useMemo(() => {
    const filterPortalItems = (items: any[], features: string[]) =>
      items.filter((item) => {
        if (!('url' in item) || !item.url) return true
        // Hide "My Inventory" from dropshippers — that tab is for fulfilment clients only
        if (item.url === '/portal/inventory' && isDropshipper) return false
        // Always show Products for dropshippers — backend guards by is_dropshipper
        if (item.url === '/portal/products' && isDropshipper) return true
        const featureKey = Object.entries(portalFeatureUrlMap).find(
          ([, url]) => url === item.url
        )?.[0]
        if (!featureKey) return true
        return features.includes(featureKey)
      })

    // "Requests" carries how much is waiting on the team, "Notifications"
    // how many are unread.
    const badges: Record<string, number> = {
      '/requests': Number(pendingRequestsCount) || 0,
      '/notifications': unreadCount,
    }
    const badged: NavGroupType[] = Object.values(badges).some((count) => count > 0)
      ? sidebarData.navGroups.map((group) => ({
          ...group,
          items: group.items.map((item) => {
            const count = item.url ? badges[item.url] : 0
            return count > 0 ? { ...item, badge: count > 99 ? '99+' : String(count) } : item
          }),
        }))
      : sidebarData.navGroups

    // Screens still behind the PIN leave their group for one of their own,
    // right below General. Not told otherwise, a screen is locked (as in useScreenLock).
    const isLocked = (item: NavGroupType['items'][number]) =>
      !!item.lock && (locks?.[item.lock] ?? true)
    const lockedItems = badged.flatMap((group) => group.items.filter(isLocked))
    const groups: NavGroupType[] = lockedItems.length
      ? badged.flatMap((group) => {
          const rest = { ...group, items: group.items.filter((item) => !isLocked(item)) }
          return group.title === 'General' ? [rest, { title: LOCKED_GROUP_TITLE, icon: Lock, items: lockedItems }] : [rest]
        })
      : badged

    // When impersonating, show only the "My Portal" group with portal feature filtering
    // and strip the `role` guard so the admin passes the role check
    if (isImpersonating) {
      const portalGroup = groups.find((g) => g.title === 'My Portal')
      if (!portalGroup) return []

      const filteredItems = filterPortalItems(portalGroup.items, portalFeatures ?? [])
        .map((item) => ({ ...item, role: undefined })) // strip role so it renders for admin

      return [{ ...portalGroup, items: filteredItems }]
    }

    if (!portalFeatures) return groups

    return groups.map((group): NavGroupType => {
      if (group.title !== 'My Portal') return group
      return { ...group, items: filterPortalItems(group.items, portalFeatures) }
    })
  }, [portalFeatures, isImpersonating, isDropshipper, pendingRequestsCount, unreadCount, locks])
}
