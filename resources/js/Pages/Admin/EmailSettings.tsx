import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { EmailGraveyard } from '@/features/admin/email-settings/email-graveyard'
import { EmailSettingsForm } from '@/features/admin/email-settings/email-settings-form'
import { EmailStatistics } from '@/features/admin/email-settings/email-statistics'
import { RecentEmailLogs } from '@/features/admin/email-settings/recent-email-logs'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePermissions } from '@/hooks/use-permissions'

export default function EmailSettings({ settings, recentLogs, graveyard }: any) {
  // The statistics and the logs are each a permission of their own.
  const { can } = usePermissions()
  return (
    <AuthenticatedLayout>
      <Head title='Email Settings' />

      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='space-y-6'>
          <div>
            <h1 className='text-3xl font-bold tracking-tight'>Email Settings</h1>
            <p className='text-muted-foreground'>
              Configure SMTP settings and manage email delivery
            </p>
          </div>

          <Tabs defaultValue='settings' className='space-y-4'>
            <TabsList>
              <TabsTrigger value='settings'>Configuration</TabsTrigger>
              {can('view email statistics') && <TabsTrigger value='statistics'>Statistics</TabsTrigger>}
              {can('view email logs') && <TabsTrigger value='logs'>Email Logs</TabsTrigger>}
              <TabsTrigger value='graveyard'>Graveyard</TabsTrigger>
            </TabsList>

            <TabsContent value='settings' className='space-y-4'>
              <EmailSettingsForm settings={settings} />
            </TabsContent>

            {can('view email statistics') && (
              <TabsContent value='statistics' className='space-y-4'>
                <EmailStatistics />
              </TabsContent>
            )}

            {can('view email logs') && (
              <TabsContent value='logs' className='space-y-4'>
                <Card>
                  <CardHeader>
                    <CardTitle>Recent Email Logs</CardTitle>
                    <CardDescription>
                      View recently sent emails and their status
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <RecentEmailLogs logs={recentLogs} />
                  </CardContent>
                </Card>
              </TabsContent>
            )}

            <TabsContent value='graveyard' className='space-y-4'>
              <Card>
                <CardHeader>
                  <CardTitle>Email Graveyard</CardTitle>
                  <CardDescription>
                    Addresses that do not exist. Nothing is sent to them until they are restored
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <EmailGraveyard entries={graveyard} />
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
