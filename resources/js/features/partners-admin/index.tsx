import { useEffect, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import { Banknote, Clock, Coins, Handshake, Inbox, Search as SearchIcon, UserCheck, Users } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { StatCard } from '@/components/stat-card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { usePermissions } from '@/hooks/use-permissions'
import { StatusBadge, TierDot } from '@/features/partners/partner-shell'
import { PARTNER_TYPE_LABEL, TIER_LABEL, formatDate, formatPoints } from '@/features/partners/types'
import { LeadsTab } from './leads-tab'
import { PartnerDetailSheet } from './partner-detail-sheet'
import { PayoutsTab } from './payouts-tab'
import { SettingsTab } from './settings-tab'
import type { LeadRow, PartnerRow, PayoutRow, Settings, Stats } from './types'

type Props = {
  partners: PartnerRow[]
  stats: Stats | null
  leads: LeadRow[] | null
  payouts: PayoutRow[] | null
  settings: Settings | null
}

const STATUS_FILTERS = ['all', 'pending', 'approved', 'suspended', 'rejected'] as const

export function PartnersAdmin({ partners: initial, stats, leads, payouts, settings }: Props) {
  const { can } = usePermissions()
  const params = useMemo(() => new URLSearchParams(window.location.search), [])

  const [tab, setTab] = useState(params.get('tab') ?? 'partners')
  const [partners, setPartners] = useState(initial)
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState<(typeof STATUS_FILTERS)[number]>((params.get('status') as never) ?? 'all')
  const [openId, setOpenId] = useState<number | null>(params.get('partner') ? Number(params.get('partner')) : null)

  useEffect(() => setPartners(initial), [initial])

  const upsert = (row: PartnerRow) => setPartners((list) => list.map((p) => (p.id === row.id ? { ...p, ...row } : p)))

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    return partners.filter((p) => {
      if (status !== 'all' && p.status !== status) return false
      if (!q) return true
      return [p.display_name, p.company_name, p.code, p.user?.email, p.user?.name, p.phone]
        .some((v) => v?.toLowerCase().includes(q))
    })
  }, [partners, query, status])

  const counts = useMemo(
    () => Object.fromEntries(STATUS_FILTERS.map((s) => [s, s === 'all' ? partners.length : partners.filter((p) => p.status === s).length])),
    [partners]
  )

  const refresh = () => router.reload({ only: ['partners', 'stats', 'leads', 'payouts'] })

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>
      <Main>
        <div className='mb-6'>
          <h1 className='flex items-center gap-2 text-2xl font-bold tracking-tight'>
            <Handshake className='h-6 w-6 text-orange-600' /> Partners
          </h1>
          <p className='text-muted-foreground'>Applications, referrals, points and payouts for the partner program.</p>
        </div>

        {stats && (
          <div className='mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7'>
            <StatCard icon={<Clock className='h-4 w-4' />} label='Pending applications' value={stats.pending} tone='orange' />
            <StatCard icon={<Handshake className='h-4 w-4' />} label='Approved partners' value={stats.approved} tone='teal' texture='dots' />
            <StatCard icon={<Users className='h-4 w-4' />} label='Referred clients' value={stats.referred_clients} tone='blue' texture='grid' />
            <StatCard icon={<UserCheck className='h-4 w-4' />} label='Active referrals' value={stats.active_clients} tone='emerald' />
            <StatCard icon={<Coins className='h-4 w-4' />} label='Points outstanding' value={stats.points_outstanding} tone='violet' texture='dots' hint='Sum of all partner balances. 1 point ≈ 1 SAR.' />
            <StatCard icon={<Banknote className='h-4 w-4' />} label='Open payouts' value={stats.open_payouts} tone='rose' texture='grid' />
            <StatCard icon={<Inbox className='h-4 w-4' />} label='New leads' value={stats.new_leads} tone='sky' />
          </div>
        )}

        <Tabs value={tab} onValueChange={setTab}>
          <TabsList>
            <TabsTrigger value='partners'>Partners {counts.pending > 0 && <Badge variant='secondary' className='ms-1'>{counts.pending} new</Badge>}</TabsTrigger>
            {leads && <TabsTrigger value='leads'>Leads</TabsTrigger>}
            {payouts && <TabsTrigger value='payouts'>Payouts</TabsTrigger>}
            {settings && <TabsTrigger value='settings'>Settings</TabsTrigger>}
          </TabsList>

          <TabsContent value='partners' className='mt-4'>
            <div className='mb-3 flex flex-wrap items-center gap-2'>
              <div className='relative'>
                <SearchIcon className='absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground' />
                <Input className='w-64 pl-8' placeholder='Search name, code, email…' value={query} onChange={(e) => setQuery(e.target.value)} />
              </div>
              <div className='flex gap-1'>
                {STATUS_FILTERS.map((s) => (
                  <button
                    key={s}
                    onClick={() => setStatus(s)}
                    className={`rounded-full px-3 py-1 text-xs font-medium capitalize ${status === s ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:text-foreground'}`}
                  >
                    {s} <span className='tabular-nums opacity-70'>{counts[s]}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className='rounded-md border'>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Partner</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Tier</TableHead>
                    <TableHead className='text-right'>Points</TableHead>
                    <TableHead className='text-right'>Referred</TableHead>
                    <TableHead className='text-right'>Active</TableHead>
                    <TableHead className='text-right'>Leads</TableHead>
                    <TableHead>Applied</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filtered.length === 0 ? (
                    <TableRow><TableCell colSpan={9} className='py-10 text-center text-muted-foreground'>No partners match.</TableCell></TableRow>
                  ) : filtered.map((p) => (
                    <TableRow key={p.id} className='cursor-pointer' onClick={() => setOpenId(p.id)}>
                      <TableCell>
                        <div className='font-medium'>{p.display_name}</div>
                        <div className='text-xs text-muted-foreground'><span className='font-mono'>{p.code}</span> · {p.user?.email}</div>
                      </TableCell>
                      <TableCell className='text-sm'>{PARTNER_TYPE_LABEL[p.type]}</TableCell>
                      <TableCell><StatusBadge status={p.status} /></TableCell>
                      <TableCell className='text-sm'><span className='inline-flex items-center gap-1.5'><TierDot tier={p.tier} />{TIER_LABEL[p.tier]}</span></TableCell>
                      <TableCell className='text-right tabular-nums'>{formatPoints(p.points_balance)}</TableCell>
                      <TableCell className='text-right tabular-nums'>{p.referred_clients_count}</TableCell>
                      <TableCell className='text-right tabular-nums'>{p.active_clients_count}</TableCell>
                      <TableCell className='text-right tabular-nums'>{p.leads_count}</TableCell>
                      <TableCell className='whitespace-nowrap text-sm'>{formatDate(p.created_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </TabsContent>

          {leads && (
            <TabsContent value='leads' className='mt-4'>
              <LeadsTab leads={leads} onChanged={refresh} />
            </TabsContent>
          )}
          {payouts && (
            <TabsContent value='payouts' className='mt-4'>
              <PayoutsTab payouts={payouts} canHandle={can('handle partner payouts')} onChanged={refresh} />
            </TabsContent>
          )}
          {settings && (
            <TabsContent value='settings' className='mt-4'>
              <SettingsTab settings={settings} />
            </TabsContent>
          )}
        </Tabs>

        <PartnerDetailSheet
          partnerId={openId}
          onOpenChange={(open) => !open && setOpenId(null)}
          onChanged={(row) => { upsert(row); refresh() }}
        />
      </Main>
    </>
  )
}
