import {
  LayoutDashboard,
  HelpCircle,
  Bell,
  Package,
  Settings,
  UserCog,
  Users,
  AudioWaveform,
  Command,
  GalleryVerticalEnd,
  ShoppingCart,
  Shield,
  Lock,
  Mail,
  PackageSearch,
  UserCircle,
} from 'lucide-react'
import { type SidebarData } from '../types'

export const sidebarData: SidebarData = {
  teams: [
    {
      name: 'KSA Drop Portal',
      logo: Command,
      plan: 'Laravel + Inertia',
    },
    {
      name: 'Acme Inc',
      logo: GalleryVerticalEnd,
      plan: 'Enterprise',
    },
    {
      name: 'Acme Corp.',
      logo: AudioWaveform,
      plan: 'Startup',
    },
  ],
  navGroups: [
    {
      title: 'General',
      items: [
        {
          title: 'Dashboard',
          url: '/',
          icon: LayoutDashboard,
          permission: 'view dashboard',
        },
        {
          title: 'Clients',
          url: '/client',
          icon: UserCircle,
          permission: 'view client',
        },
        {
          title: 'Inventory',
          url: '/inventory',
          icon: PackageSearch,
          permission: 'view inventory',
        },
        {
          title: 'Orders',
          url: '/orders',
          icon: ShoppingCart,
          permission: 'view orders',
        },
        {
          title: 'Apps',
          url: '/apps',
          icon: Package,
          permission: 'view apps',
        },
      ],
    },
    {
      title: 'Team Management',
      items: [
        {
          title: 'Users',
          url: '/team-management/users',
          icon: UserCog,
          permission: 'view users',
        },
        {
          title: 'Roles',
          url: '/team-management/roles',
          icon: Shield,
          permission: 'view roles',
        },
        {
          title: 'Permissions',
          url: '/team-management/permissions',
          icon: Lock,
          permission: 'view permissions',
        },
      ],
    },
    {
      title: 'Other',
      items: [
        {
          title: 'Notifications',
          url: '/notifications',
          icon: Bell,
        },
        {
          title: 'Settings',
          icon: Settings,
          items: [
            {
              title: 'Profile',
              url: '/settings',
              icon: UserCog,
            },
            {
              title: 'Email',
              url: '/settings/email',
              icon: Mail,
            },
            {
              title: 'Security',
              url: '/settings/security',
              icon: Shield,
            },
            {
              title: 'Toasts',
              url: '/settings/notifications',
              icon: Bell,
            },
          ],
        },
      ],
    },
  ],
}
