import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Client as ClientFeature } from '@/features/client'

export default function ClientPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Clients' />
      <ClientFeature />
    </AuthenticatedLayout>
  )
}
