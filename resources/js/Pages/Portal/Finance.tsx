import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalFinance } from '@/features/portal/finance'

export default function PortalFinancePage() {
  return (
    <AuthenticatedLayout>
      <Head title='Finance' />
      <PortalFinance />
    </AuthenticatedLayout>
  )
}
