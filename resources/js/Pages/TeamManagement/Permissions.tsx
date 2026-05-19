import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Permissions as PermissionsFeature } from '@/features/permissions'

interface Permission {
  id: number
  name: string
  roles: string[]
  created_at: string
  updated_at: string
}

interface Props {
  permissions: Permission[]
}

export default function TeamManagementPermissions() {
  const { permissions } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title='Permissions - Team Management' />
      <PermissionsFeature permissions={permissions} />
    </AuthenticatedLayout>
  )
}
