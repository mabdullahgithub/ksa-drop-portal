import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PortalSettings } from '@/features/portal/settings'
import { CompanyProfileForm } from '@/features/portal/settings/components/company-profile-form'

export default function PortalCompanyProfilePage() {
  return (
    <AuthenticatedLayout>
      <Head title='Company Profile' />
      <PortalSettings>
        <CompanyProfileForm />
      </PortalSettings>
    </AuthenticatedLayout>
  )
}
