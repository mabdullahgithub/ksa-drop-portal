import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Settings } from '@/features/settings'
import { SettingsNotifications } from '@/features/settings/notifications'

export default function Notifications() {
  return (
    <AuthenticatedLayout>
      <Head title='Settings - Toasts' />
      <Settings>
        <SettingsNotifications />
      </Settings>
    </AuthenticatedLayout>
  )
}
