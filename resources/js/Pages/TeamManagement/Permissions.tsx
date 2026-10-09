import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Permissions as PermissionsFeature } from '@/features/permissions'
import { type CatalogModule } from '@/features/roles/data/catalog'
import { type PageProps } from '@/types'

export default function TeamManagementPermissions() {
  const { catalog, roles } = usePage<PageProps<{ catalog: CatalogModule[]; roles: Record<string, string[]> }>>().props

  return (
    <AuthenticatedLayout>
      <Head title='Permissions - Team Management' />
      <PermissionsFeature catalog={catalog} roles={roles} />
    </AuthenticatedLayout>
  )
}
