import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ThemeSwitch } from '@/components/theme-switch'
import { UsersDialogs } from './components/users-dialogs'
import { UsersPrimaryButtons } from './components/users-primary-buttons'
import { UsersProvider } from './components/users-provider'
import { UsersTable } from './components/users-table'

interface User {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin?: boolean
  is_client?: boolean
  created_at: string
}

interface UsersProps {
  users: User[]
  availableRoles?: string[]
  availablePermissions?: string[]
}

export function Users({ users, availableRoles, availablePermissions }: UsersProps) {
  const team = users.filter((u) => !u.is_client)
  const clients = users.filter((u) => u.is_client)
  const tabs = [
    { value: 'team', label: 'Team', rows: team },
    { value: 'clients', label: 'Clients', rows: clients },
  ]

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
              View and manage user accounts and role assignments.
            </p>
          </div>
          <UsersPrimaryButtons />
        </div>
        <Tabs defaultValue='team' className='flex flex-1 flex-col gap-4'>
          <TabsList className='w-fit'>
            {tabs.map((tab) => (
              <TabsTrigger key={tab.value} value={tab.value} className='gap-1.5'>
                {tab.label}
                <Badge variant='secondary' className='px-1.5 text-[10px]'>
                  {tab.rows.length}
                </Badge>
              </TabsTrigger>
            ))}
          </TabsList>
          {tabs.map((tab) => (
            <TabsContent key={tab.value} value={tab.value}>
              <UsersTable data={tab.rows} availableRoles={availableRoles} />
            </TabsContent>
          ))}
        </Tabs>
      </Main>

      <UsersDialogs availableRoles={availableRoles} availablePermissions={availablePermissions} />
    </UsersProvider>
  )
}
