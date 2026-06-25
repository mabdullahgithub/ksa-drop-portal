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
  TrendingUp,
  Tag,
  Wallet,
  Truck,
  Plug,
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
          url: '/dashboard',
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
          title: 'Track Shipment',
          url: '/track',
          icon: Truck,
          permission: 'view orders',
          newTab: true,
        },
        {
          title: 'Connectors',
          url: '/apps',
          icon: Package,
          permission: 'view apps',
        },
        {
          title: 'Tags',
          url: '/tags',
          icon: Tag,
          permission: 'view tags',
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
      title: 'My Portal',
      items: [
        {
          title: 'Dashboard',
          url: '/portal',
          icon: LayoutDashboard,
          role: 'client',
        },
        {
          title: 'My Orders',
          url: '/portal/orders',
          icon: ShoppingCart,
          role: 'client',
        },
        {
          title: 'My Inventory',
          url: '/portal/inventory',
          icon: PackageSearch,
          role: 'client',
        },
        {
          title: 'Products',
          url: '/portal/products',
          icon: Package,
          role: 'client',
        },
        {
          title: 'Integrations',
          url: '/portal/connectors',
          icon: Plug,
          role: 'client',
        },
        {
          title: 'Revenue',
          url: '/portal/revenue',
          icon: TrendingUp,
          role: 'client',
        },
        {
          title: 'Finance',
          url: '/portal/finance',
          icon: Wallet,
          role: 'client',
        },
        {
          title: 'Notifications',
          url: '/notifications',
          icon: Bell,
          role: 'client',
        },
        {
          title: 'Settings',
          url: '/portal/settings',
          icon: Settings,
          role: 'client',
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
          permission: 'view dashboard',
        },
        {
          title: 'Settings',
          icon: Settings,
          permission: 'view settings',
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
