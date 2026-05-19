import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Settings } from '@/features/settings'
import { SettingsProfile } from '@/features/settings/profile'

export default function Profile() {
  return (
    <AuthenticatedLayout>
      <Head title='Settings - Profile' />
      <Settings>
        <SettingsProfile />
      </Settings>
    </AuthenticatedLayout>
  )
}
