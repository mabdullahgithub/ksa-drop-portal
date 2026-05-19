import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { RolesDialogs } from './components/roles-dialogs'
import { RolesPrimaryButtons } from './components/roles-primary-buttons'
import { RolesProvider } from './components/roles-provider'
import { RolesTable } from './components/roles-table'
import { type Role } from './data/schema'

interface RolesProps {
  roles: Role[]
  availablePermissions: string[]
}

export function Roles({ roles, availablePermissions }: RolesProps) {
  return (
    <RolesProvider>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ConfigDrawer />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div className='space-y-1'>
            <h2 className='text-3xl font-bold tracking-tight'>Roles</h2>
            <p className='text-muted-foreground'>
              Manage roles and assign permissions to control access levels.
            </p>
          </div>
          <RolesPrimaryButtons />
        </div>
        <RolesTable data={roles} />
      </Main>

      <RolesDialogs availablePermissions={availablePermissions} />
    </RolesProvider>
  )
}
