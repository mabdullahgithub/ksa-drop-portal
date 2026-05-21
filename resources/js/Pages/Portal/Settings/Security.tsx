import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalSettings } from '@/features/portal/settings'
import { SecurityForm } from '@/features/settings/security/security-form'

export default function PortalSecurityPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Security' />
      <PortalSettings>
        <SecurityForm />
      </PortalSettings>
    </AuthenticatedLayout>
  )
}
