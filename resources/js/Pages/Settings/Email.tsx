import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Settings } from '@/features/settings'
import { SettingsEmail } from '@/features/settings/email'

export default function Email() {
  return (
    <AuthenticatedLayout>
      <Head title='Settings - Email' />
      <Settings>
        <SettingsEmail />
      </Settings>
    </AuthenticatedLayout>
  )
}
