import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { ClientDetailPage } from '@/features/client/client-detail-page'
import type { PageProps } from '@/types'
import type { Client } from '@/types/client'

interface Props extends PageProps {
  client: Client
}

export default function ClientShowPage() {
  const { client } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title={client.company_name} />
      <ClientDetailPage client={client} />
    </AuthenticatedLayout>
  )
}
