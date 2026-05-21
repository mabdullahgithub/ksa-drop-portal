import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalProducts } from '@/features/portal/products'

export default function PortalProductsPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Products' />
      <PortalProducts />
    </AuthenticatedLayout>
  )
}
