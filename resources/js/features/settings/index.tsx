import { Shield, Bell, UserCog, Mail } from 'lucide-react'
import { Separator } from '@/components/ui/separator'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { SidebarNav } from './components/sidebar-nav'

const sidebarNavItems = [
  {
    title: 'Profile',
    href: '/settings',
    icon: <UserCog size={18} />,
  },
  {
    title: 'Email',
    href: '/settings/email',
    icon: <Mail size={18} />,
  },
  {
    title: 'Security',
    href: '/settings/security',
    icon: <Shield size={18} />,
  },
  {
    title: 'Toasts',
    href: '/settings/notifications',
    icon: <Bell size={18} />,
  },
]

export function Settings({ children }: { children?: React.ReactNode }) {
  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ConfigDrawer />
        <ProfileDropdown />
      </Header>

      <Main fixed>
        <div className='space-y-0.5 shrink-0'>
          <h1 className='text-2xl font-bold tracking-tight md:text-3xl'>
            Settings
          </h1>
          <p className='text-muted-foreground'>
            Manage your account settings and set e-mail preferences.
          </p>
        </div>
        <Separator className='my-4 lg:my-6 shrink-0' />
        <div className='flex flex-1 flex-col space-y-2 md:space-y-2 lg:flex-row lg:space-y-0 lg:space-x-12 min-h-0'>
          <aside className='lg:w-1/5 shrink-0'>
            <SidebarNav items={sidebarNavItems} />
          </aside>
          <div className='flex-1 overflow-y-auto overflow-x-hidden overscroll-contain p-1 min-h-0'>
            {children}
          </div>
        </div>
      </Main>
    </>
  )
}
