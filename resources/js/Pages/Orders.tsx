import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Orders as OrdersFeature } from '@/features/orders'

export default function Orders() {
  return (
    <AuthenticatedLayout>
      <Head title='Orders' />
      <OrdersFeature />
    </AuthenticatedLayout>
  )
}
