import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalInventory } from '@/features/portal/inventory'

export default function PortalInventoryPage() {
  return (
    <AuthenticatedLayout>
      <Head title='My Inventory' />
      <PortalInventory />
    </AuthenticatedLayout>
  )
}
