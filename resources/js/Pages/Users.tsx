import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Users as UsersFeature } from '@/features/users'

export default function Users() {
  return (
    <AuthenticatedLayout>
      <Head title='Users' />
      <UsersFeature />
    </AuthenticatedLayout>
  )
}
