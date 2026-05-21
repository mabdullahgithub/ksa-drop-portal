import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { UsersDialogs } from './components/users-dialogs'
import { UsersPrimaryButtons } from './components/users-primary-buttons'
import { UsersProvider } from './components/users-provider'
import { UsersTable } from './components/users-table'
import { users as mockUsers } from './data/users'

interface User {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin?: boolean
  created_at: string
}

interface UsersProps {
  users?: User[]
  availableRoles?: string[]
  availablePermissions?: string[]
}

export function Users({ users, availableRoles, availablePermissions }: UsersProps = {}) {
  const userData = users || mockUsers

  return (
    <UsersProvider>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div className='space-y-1'>
            <h2 className='text-3xl font-bold tracking-tight'>Users</h2>
            <p className='text-muted-foreground'>
              {users
                ? 'View and manage user accounts and role assignments.'
                : 'Manage your users and their roles here.'}
            </p>
          </div>
          <UsersPrimaryButtons />
        </div>
        <UsersTable data={userData} availableRoles={availableRoles} />
      </Main>

      <UsersDialogs availableRoles={availableRoles} availablePermissions={availablePermissions} />
    </UsersProvider>
  )
}
