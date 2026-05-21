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
  revenue: '/portal/revenue',
  finance: '/portal/finance',
}

export function AppSidebar() {
  const { collapsible, variant } = useLayout()
  const { auth } = usePage().props as any
  const portalFeatures: string[] | null = auth?.portal_features

  const navGroups = useMemo(() => {
    if (!portalFeatures) return sidebarData.navGroups

    return sidebarData.navGroups.map((group): NavGroupType => {
      if (group.title !== 'My Portal') return group

      const filteredItems = group.items.filter((item) => {
        if (!('url' in item) || !item.url) return true
        const featureKey = Object.entries(portalFeatureUrlMap).find(
          ([, url]) => url === item.url
        )?.[0]
        if (!featureKey) return true
        return portalFeatures.includes(featureKey)
      })

      return { ...group, items: filteredItems }
    })
  }, [portalFeatures])

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
        <NavUser user={auth.user} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
