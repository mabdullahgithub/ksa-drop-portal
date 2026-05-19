import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Inventory as InventoryFeature } from '@/features/inventory'

export default function InventoryPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Inventory' />
      <InventoryFeature />
    </AuthenticatedLayout>
  )
}
