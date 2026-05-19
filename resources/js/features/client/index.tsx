import { UserCircle } from 'lucide-react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'

export function Client() {
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
        <div className='mb-4'>
          <h1 className='text-2xl font-bold tracking-tight'>Clients</h1>
          <p className='text-muted-foreground'>
            Manage all your clients
          </p>
        </div>

        <Card>
          <CardHeader>
            <div className='flex items-center gap-2'>
              <UserCircle className='h-5 w-5' />
              <CardTitle>Client Management</CardTitle>
            </div>
            <CardDescription>
              View and manage your clients here
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className='flex flex-col items-center justify-center py-12 text-center'>
              <UserCircle className='h-16 w-16 text-muted-foreground/50 mb-4' />
              <h3 className='text-lg font-semibold mb-2'>No clients yet</h3>
              <p className='text-sm text-muted-foreground max-w-md'>
                Admin will be able to add clients and manage them here.
                The client management features will be available soon.
              </p>
            </div>
          </CardContent>
        </Card>
      </Main>
    </>
  )
}
