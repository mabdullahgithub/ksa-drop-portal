import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalRevenue } from '@/features/portal/revenue'

export default function PortalRevenuePage() {
  return (
    <AuthenticatedLayout>
      <Head title='Revenue' />
      <PortalRevenue />
    </AuthenticatedLayout>
  )
}
