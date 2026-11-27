import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalSupport } from '@/features/portal/support'

export default function PortalSupportPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Support' />
      <PortalSupport />
    </AuthenticatedLayout>
  )
}
