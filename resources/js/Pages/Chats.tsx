import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Chats as ChatsFeature } from '@/features/chats'

export default function Chats() {
  return (
    <AuthenticatedLayout>
      <Head title='Chats' />
      <ChatsFeature />
    </AuthenticatedLayout>
  )
}
