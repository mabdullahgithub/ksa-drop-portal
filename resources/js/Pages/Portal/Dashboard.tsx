import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalDashboard } from '@/features/portal/dashboard'

export default function PortalDashboardPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Portal Dashboard' />
      <PortalDashboard />
    </AuthenticatedLayout>
  )
}
