import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Users as UsersFeature } from '@/features/users'

interface User {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin: boolean
  is_client: boolean
  client_access: 'all' | 'dropshippers' | 'fulfilment' | 'assigned'
  client_ids: number[]
  created_at: string
}

interface Props {
  users: User[]
  roles: string[]
  permissions: string[]
  online: number[]
}

export default function TeamManagementUsers() {
  const { users, roles, online } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title='Users - Team Management' />
      <UsersFeature users={users} availableRoles={roles} online={online} />
    </AuthenticatedLayout>
  )
}
