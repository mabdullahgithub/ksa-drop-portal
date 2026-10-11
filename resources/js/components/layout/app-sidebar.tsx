import { useLayout } from '@/context/layout-provider'
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarRail,
} from '@/components/ui/sidebar'
import { AppLogo } from './app-logo'
// import { AppTitle } from './app-title'
import { NavGroup, NavPinned } from './nav-group'
import { TeamSwitcher } from './team-switcher'
import { useNavGroups } from '@/hooks/use-nav-groups'

export function AppSidebar() {
  const { collapsible, variant } = useLayout()
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
      <NavPinned items={navGroups.flatMap((group) => group.items)} />
      <SidebarRail />
    </Sidebar>
  )
}
