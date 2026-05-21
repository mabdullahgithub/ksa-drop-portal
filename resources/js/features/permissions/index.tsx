import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { PermissionsDialogs } from './components/permissions-dialogs'
import { PermissionsProvider } from './components/permissions-provider'
import { PermissionsGrid } from './components/permissions-grid'
import { type Permission } from './data/schema'
import { AlertCircle } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'

interface PermissionsProps {
  permissions: Permission[]
}

export function Permissions({ permissions }: PermissionsProps) {
  return (
    <PermissionsProvider>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='space-y-1'>
          <h2 className='text-3xl font-bold tracking-tight'>Permissions</h2>
          <p className='text-muted-foreground'>
            View and manage system permissions. Permissions are assigned to roles.
          </p>
        </div>

        <Alert>
          <AlertCircle className='h-4 w-4' />
          <AlertTitle>Read-only View</AlertTitle>
          <AlertDescription>
            Permissions are managed by system administrators and can only be edited, not created or deleted.
          </AlertDescription>
        </Alert>

        <PermissionsGrid data={permissions} />
      </Main>

      <PermissionsDialogs />
    </PermissionsProvider>
  )
}
