import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Apps as AppsFeature } from '@/features/apps'

export default function Apps() {
  return (
    <AuthenticatedLayout>
      <Head title='Apps' />
      <AppsFeature />
    </AuthenticatedLayout>
  )
}
