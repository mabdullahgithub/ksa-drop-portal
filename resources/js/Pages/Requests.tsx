import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Requests as RequestsFeature } from '@/features/requests'

export default function RequestsPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Requests' />
      <RequestsFeature />
    </AuthenticatedLayout>
  )
}
