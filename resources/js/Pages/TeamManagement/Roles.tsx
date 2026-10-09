import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Roles as RolesFeature } from '@/features/roles'

interface Role {
  id: number
  name: string
  permissions: string[]
  users_count: number
  is_super_admin: boolean
  is_protected: boolean
  created_at: string
  updated_at: string
}

interface Props {
  roles: Role[]
  permissions: string[]
}

export default function TeamManagementRoles() {
  const { roles } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title='Roles - Team Management' />
      <RolesFeature roles={roles} />
    </AuthenticatedLayout>
  )
}
