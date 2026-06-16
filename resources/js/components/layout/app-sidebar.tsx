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
import { NavGroup } from './nav-group'
import { NavUser } from './nav-user'
import { TeamSwitcher } from './team-switcher'
import { useNavGroups } from '@/hooks/use-nav-groups'

export function AppSidebar() {
  const { collapsible, variant } = useLayout()
  const { auth } = usePage().props as any
  const portalFeatures: string[] | null = auth?.portal_features
  const isImpersonating: boolean = !!auth?.impersonating
  const navGroups = useNavGroups()

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
