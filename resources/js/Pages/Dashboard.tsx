import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Dashboard } from '@/features/dashboard'

export default function DashboardPage() {
  return (
    <AuthenticatedLayout>
      <Head title='Dashboard' />
      <Dashboard />
    </AuthenticatedLayout>
  )
}
