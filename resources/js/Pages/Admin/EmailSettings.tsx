import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { EmailSettingsForm } from '@/features/admin/email-settings/email-settings-form'
import { EmailStatistics } from '@/features/admin/email-settings/email-statistics'
import { RecentEmailLogs } from '@/features/admin/email-settings/recent-email-logs'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

export default function EmailSettings({ settings, recentLogs }: any) {
  return (
    <AuthenticatedLayout>
      <Head title='Email Settings' />

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
            <TabsTrigger value='statistics'>Statistics</TabsTrigger>
            <TabsTrigger value='logs'>Email Logs</TabsTrigger>
          </TabsList>

          <TabsContent value='settings' className='space-y-4'>
            <EmailSettingsForm settings={settings} />
          </TabsContent>

          <TabsContent value='statistics' className='space-y-4'>
            <EmailStatistics />
          </TabsContent>

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
        </Tabs>
      </div>
    </AuthenticatedLayout>
  )
}
