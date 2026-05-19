import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Settings } from '@/features/settings'
import { SettingsSecurity } from '@/features/settings/security'

export default function Security() {
  return (
    <AuthenticatedLayout>
      <Head title='Settings - Security' />
      <Settings>
        <SettingsSecurity />
      </Settings>
    </AuthenticatedLayout>
  )
}
