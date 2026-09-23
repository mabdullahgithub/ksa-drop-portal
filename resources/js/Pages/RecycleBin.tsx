import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { RecycleBin as RecycleBinFeature } from '@/features/recycle-bin'

export default function RecycleBinPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Recycle Bin' />
      <RecycleBinFeature />
    </AuthenticatedLayout>
  )
}
