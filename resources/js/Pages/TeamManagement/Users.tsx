import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Users as UsersFeature } from '@/features/users'

interface User {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin: boolean
  created_at: string
}

interface Props {
  users: User[]
  roles: string[]
  permissions: string[]
}

export default function TeamManagementUsers() {
  const { users, roles, permissions } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title='Users - Team Management' />
      <UsersFeature users={users} availableRoles={roles} availablePermissions={permissions} />
    </AuthenticatedLayout>
  )
}
