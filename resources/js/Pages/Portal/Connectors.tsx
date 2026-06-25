import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalConnectors } from '@/features/portal/connectors'

export default function PortalConnectorsPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Integrations' />
      <PortalConnectors />
    </AuthenticatedLayout>
  )
}
