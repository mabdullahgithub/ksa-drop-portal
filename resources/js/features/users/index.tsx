import { useMemo, useState } from 'react'
import { usePoll } from '@inertiajs/react'
import { LayoutGrid, Table2 } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePermissions } from '@/hooks/use-permissions'
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
  client_access?: 'all' | 'assigned'
  client_ids?: number[]
  created_at: string
}

interface UsersProps {
  users: User[]
  availableRoles?: string[]
  /** The ids of the users on the portal right now. */
  online?: number[]
}

export function Users({ users, availableRoles, online }: UsersProps) {
  // Kept the same between renders: new rows would send the table back to page one.
  const team = useMemo(() => users.filter((u) => !u.is_client), [users])
  const clients = useMemo(() => users.filter((u) => u.is_client), [users])
  const { can } = usePermissions()
  const [view, setView] = useState<'grid' | 'table'>('table')
  const tabs = [
    { value: 'team', label: 'Team', rows: team },
    // The client sign-in accounts are a permission of their own.
    ...(can('view client accounts') ? [{ value: 'clients', label: 'Clients', rows: clients }] : []),
  ]

  // Who is online, refreshed every minute; nothing else is reloaded.
  usePoll(60_000, { only: ['online'] })

  return (
    <UsersProvider online={online}>
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
              Manage team members, their roles and the clients each one handles.
            </p>
          </div>
          <div className='flex items-center gap-2'>
            {/* View toggle */}
            <div className='flex items-center rounded-md border p-0.5'>
              <Button
                variant={view === 'grid' ? 'secondary' : 'ghost'}
                size='icon'
                className='h-7 w-7'
                onClick={() => setView('grid')}
                title='Card view'
              >
                <LayoutGrid size={14} />
              </Button>
              <Button
                variant={view === 'table' ? 'secondary' : 'ghost'}
                size='icon'
                className='h-7 w-7'
                onClick={() => setView('table')}
                title='Table view'
              >
                <Table2 size={14} />
              </Button>
            </div>
            <UsersPrimaryButtons />
          </div>
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
              <UsersTable data={tab.rows} availableRoles={availableRoles} view={view} />
            </TabsContent>
          ))}
        </Tabs>
      </Main>

      <UsersDialogs availableRoles={availableRoles} />
    </UsersProvider>
  )
}
