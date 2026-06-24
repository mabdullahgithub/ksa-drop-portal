import { useMemo } from 'react'
import { usePage } from '@inertiajs/react'
import { sidebarData } from '@/components/layout/data/sidebar-data'
import type { NavGroup as NavGroupType } from '@/components/layout/types'

const portalFeatureUrlMap: Record<string, string> = {
  orders: '/portal/orders',
  inventory: '/portal/inventory',
  products: '/portal/products',
  revenue: '/portal/revenue',
  finance: '/portal/finance',
}

/**
 * Resolves the nav groups for the current user, applying portal-feature,
 * impersonation and dropshipper rules. Permission/role filtering is still
 * handled per-item by the consumer (NavGroup / command menu).
 *
 * Shared by the sidebar and the command menu so both stay in sync.
 */
export function useNavGroups(): NavGroupType[] {
  const { auth } = usePage().props as any
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

    // When impersonating, show only the "My Portal" group with portal feature filtering
    // and strip the `role` guard so the admin passes the role check
    if (isImpersonating) {
      const portalGroup = sidebarData.navGroups.find((g) => g.title === 'My Portal')
      if (!portalGroup) return []

      const filteredItems = filterPortalItems(portalGroup.items, portalFeatures ?? [])
        .map((item) => ({ ...item, role: undefined })) // strip role so it renders for admin

      return [{ ...portalGroup, items: filteredItems }]
    }

    if (!portalFeatures) return sidebarData.navGroups

    return sidebarData.navGroups.map((group): NavGroupType => {
      if (group.title !== 'My Portal') return group
      return { ...group, items: filterPortalItems(group.items, portalFeatures) }
    })
  }, [portalFeatures, isImpersonating, isDropshipper])
}
