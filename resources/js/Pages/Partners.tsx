import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PartnersAdmin } from '@/features/partners-admin'
import type { LeadRow, PartnerRow, PayoutRow, Settings, Stats } from '@/features/partners-admin/types'
import type { PageProps } from '@/types'

type Props = PageProps<{
  partners: PartnerRow[]
  stats: Stats | null
  leads: LeadRow[] | null
  payouts: PayoutRow[] | null
  settings: Settings | null
}>

export default function Partners() {
  const { partners, stats, leads, payouts, settings } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title='Partners' />
      <PartnersAdmin partners={partners} stats={stats} leads={leads} payouts={payouts} settings={settings} />
    </AuthenticatedLayout>
  )
}
