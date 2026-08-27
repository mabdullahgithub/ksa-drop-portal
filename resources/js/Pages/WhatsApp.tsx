import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { WhatsAppInbox } from '@/features/whatsapp'

export default function WhatsApp() {
  return (
    <AuthenticatedLayout>
      <Head title='WhatsApp' />
      <WhatsAppInbox />
    </AuthenticatedLayout>
  )
}
