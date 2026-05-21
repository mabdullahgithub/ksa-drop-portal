import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalOrders } from '@/features/portal/orders'

export default function PortalOrdersPage() {
  return (
    <AuthenticatedLayout>
      <Head title='My Orders' />
      <PortalOrders />
    </AuthenticatedLayout>
  )
}
