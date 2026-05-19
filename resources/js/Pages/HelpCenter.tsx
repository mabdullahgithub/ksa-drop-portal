import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { ComingSoon } from '@/components/coming-soon'

export default function HelpCenter() {
  return (
    <AuthenticatedLayout>
      <Head title='Support' />
      <ComingSoon />
    </AuthenticatedLayout>
  )
}
