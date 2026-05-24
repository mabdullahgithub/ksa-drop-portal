import { useMemo } from 'react'
import { usePage } from '@inertiajs/react'
import { useLayout } from '@/context/layout-provider'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from '@/components/ui/sidebar'
import { AppLogo } from './app-logo'
// import { AppTitle } from './app-title'
import { sidebarData } from './data/sidebar-data'
import { NavGroup } from './nav-group'
import { NavUser } from './nav-user'
import { TeamSwitcher } from './team-switcher'
import type { NavGroup as NavGroupType } from './types'

const portalFeatureUrlMap: Record<string, string> = {
  orders: '/portal/orders',
  inventory: '/portal/inventory',
  products: '/portal/products',
  revenue: '/portal/revenue',
  finance: '/portal/finance',
}

export function AppSidebar() {
  const { collapsible, variant } = useLayout()
  const { auth } = usePage().props as any
  const portalFeatures: string[] | null = auth?.portal_features
  const isImpersonating: boolean = !!auth?.impersonating
  const isDropshipper: boolean = !!auth?.client?.is_dropshipper

  const filterPortalItems = (items: any[], features: string[]) =>
    items.filter((item) => {
      if (!('url' in item) || !item.url) return true
      // Hide "My Inventory" from dropshippers — that tab is for fulfilment clients only
      if (item.url === '/portal/inventory' && isDropshipper) return false
      const featureKey = Object.entries(portalFeatureUrlMap).find(
        ([, url]) => url === item.url
      )?.[0]
      if (!featureKey) return true
      return features.includes(featureKey)
    })

  const navGroups = useMemo(() => {
    // When impersonating, show only the "My Portal" group with portal feature filtering
    // and strip the `role` guard so the admin passes NavGroup's role check
    if (isImpersonating) {
      const portalGroup = sidebarData.navGroups.find((g) => g.title === 'My Portal')
      if (!portalGroup) return []

      const filteredItems = filterPortalItems(portalGroup.items, portalFeatures ?? [])
        .map((item) => ({ ...item, role: undefined })) // strip role so NavGroup renders it for admin

      return [{ ...portalGroup, items: filteredItems }]
    }

    if (!portalFeatures) return sidebarData.navGroups

    return sidebarData.navGroups.map((group): NavGroupType => {
      if (group.title !== 'My Portal') return group
      return { ...group, items: filterPortalItems(group.items, portalFeatures) }
    })
  }, [portalFeatures, isImpersonating, isDropshipper])

  return (
    <Sidebar collapsible={collapsible} variant={variant}>
      <SidebarHeader>
        <AppLogo />

        {/* Uncomment below to use TeamSwitcher dropdown instead of logo */}
        {/* <TeamSwitcher teams={sidebarData.teams} /> */}

        {/* Replace <TeamSwitch /> with the following <AppTitle />
         /* if you want to use the normal app title instead of TeamSwitch dropdown */}
        {/* <AppTitle /> */}
      </SidebarHeader>
      <SidebarContent>
        {navGroups.map((props) => (
          <NavGroup key={props.title} {...props} />
        ))}
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={auth.user} disableDropdown={!!portalFeatures || isImpersonating} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
