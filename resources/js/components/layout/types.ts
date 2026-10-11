import type { LockedScreen } from '@/hooks/use-screen-lock'

type Team = {
  name: string
  logo: React.ElementType
  plan: string
}

type BaseNavItem = {
  title: string
  badge?: string
  icon?: React.ElementType
  permission?: string | string[]
  role?: string | string[]
  newTab?: boolean
  // Stuck to the bottom of the sidebar in the brand orange, out of its section.
  pinned?: boolean
  // The screen sits behind the PIN: it moves to the locked section while it does.
  lock?: LockedScreen
}

type NavLink = BaseNavItem & {
  url: string
  items?: never
}

type NavCollapsible = BaseNavItem & {
  items: (BaseNavItem & { url: string; permission?: string | string[]; role?: string | string[] })[]
  url?: never
}

type NavItem = NavCollapsible | NavLink

type NavGroup = {
  title: string
  // Shown at the far end of the section title, where a folding one has its arrow.
  icon?: React.ElementType
  items: NavItem[]
  // The section can be folded away under its title; it starts open.
  collapsible?: boolean
}

type SidebarData = {
  teams: Team[]
  navGroups: NavGroup[]
}

export type { SidebarData, NavGroup, NavItem, NavCollapsible, NavLink }
