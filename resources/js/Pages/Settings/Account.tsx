import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Settings } from '@/features/settings'
import { SettingsAccount } from '@/features/settings/account'

export default function Account() {
  return (
    <AuthenticatedLayout>
      <Head title='Settings - Account' />
      <Settings>
        <SettingsAccount />
      </Settings>
    </AuthenticatedLayout>
  )
}
