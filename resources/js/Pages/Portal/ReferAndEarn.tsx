import { useMemo, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import {
  AlertTriangle, ArrowRight, BadgeCheck, Check, ChevronLeft, ChevronRight, Clock, Coins, Copy, Gift, Handshake,
  Link2, Mail, Megaphone, MessageCircle, PackageCheck, Search as SearchIcon, Sparkles, Ticket, TrendingUp, Users, Wallet,
} from 'lucide-react'
import { toast } from 'sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { StatusBadge } from '@/features/partners/partner-shell'
import { ProgramTable, TierTable } from '@/features/partners/program-table'
import { TIER_LABEL, formatDate, formatPoints, type PartnerSummary, type ProgramSummary } from '@/features/partners/types'
import { cn } from '@/lib/utils'

type ReferredClient = { id: number; name: string; activated: boolean; referred_at: string | null; earning_until: string | null }
type PayoutRow = { id: number; points: number; status: string; amount_paid: string | number | null; currency: string | null; paid_at: string | null; created_at: string }

type Props = {
  program: ProgramSummary
  state: 'none' | 'pending' | 'approved' | 'rejected' | 'suspended'
  canManage: boolean
  partner: PartnerSummary | null
  stats?: {
    available_points: number
    referred_clients: number
    active_clients: number
    leads: number
    points_this_month: number
    tier: string
    multiplier: number
    next_tier: { name: string; at: number } | null
    min_redeem_points: number
    total_paid_points: number
  }
  hasPayoutMethod?: boolean
  clients?: ReferredClient[]
  payouts?: PayoutRow[]
}

const TABS = [
  { id: 'refer', label: 'Refer & earn' },
  { id: 'earnings', label: 'Earnings' },
  { id: 'program', label: 'Program' },
] as const
type TabId = (typeof TABS)[number]['id']

function initialTab(): TabId {
  try {
    const t = new URLSearchParams(window.location.search).get('tab')
    return TABS.some((x) => x.id === t) ? (t as TabId) : 'refer'
  } catch {
    return 'refer'
  }
}

export default function ReferAndEarn(props: Props) {
  const { state, partner, program } = props

  return (
    <AuthenticatedLayout>
      <Head title='Refer & Earn' />
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>
      <Main>
        <div className='mx-auto w-full max-w-5xl'>
          <div className='mb-6 flex flex-wrap items-center gap-3'>
            <h1 className='text-2xl font-bold tracking-tight'>Refer &amp; Earn</h1>
            {state === 'approved' && partner && (
              <Badge variant='outline' className='gap-1.5 border-orange-500/40 bg-orange-500/10 text-orange-700 dark:text-orange-300'>
                <BadgeCheck className='h-3.5 w-3.5' /> {TIER_LABEL[partner.tier]} partner
              </Badge>
            )}
          </div>

          {state === 'approved' && partner && props.stats ? (
            <ApprovedView {...props} partner={partner} stats={props.stats} />
          ) : (
            <NotJoinedView state={state} program={program} canManage={props.canManage} />
          )}
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}

/* ── Not an approved partner yet ─────────────────────────────────────────── */

function NotJoinedView({ state, program, canManage }: { state: Props['state']; program: ProgramSummary; canManage: boolean }) {
  const copy = {
    none: { title: 'Earn points for every seller you bring to KSA Drop', body: 'Share your personal link. When a seller you refer ships their first order you are rewarded, and again on every delivery for the next ' + program.delivery_months + ' months.' },
    pending: { title: 'Your application is under review', body: 'Our team is reviewing your partner application. Your referral link goes live the moment it is approved.' },
    rejected: { title: 'Your application was not approved', body: 'You can read the reason on the status page and apply again if your situation changes.' },
    suspended: { title: 'Your partner account is paused', body: 'Referral earnings are on hold. Contact our team if you think this is a mistake.' },
  }[state === 'approved' ? 'none' : state]

  return (
    <div className='space-y-8'>
      <HeroShell>
        <div className='mx-auto max-w-2xl text-center'>
          <h2 className='text-balance text-3xl font-semibold tracking-tight sm:text-4xl'>{copy.title}</h2>
          <p className='mx-auto mt-3 max-w-xl text-balance text-muted-foreground'>{copy.body}</p>
          <div className='mt-6 flex flex-wrap justify-center gap-3'>
            {state === 'none' && canManage && (
              <Button asChild size='lg' className='bg-orange-600 text-white hover:bg-orange-700'>
                <Link href='/portal/partner/join'>Join the partner program <ArrowRight className='h-4 w-4' /></Link>
              </Button>
            )}
            {state === 'none' && !canManage && <p className='text-sm text-muted-foreground'>The client must join from their own account.</p>}
            {(state === 'pending' || state === 'rejected' || state === 'suspended') && (
              <Button asChild size='lg' className='bg-orange-600 text-white hover:bg-orange-700'>
                <Link href='/partners/pending'>View application status</Link>
              </Button>
            )}
          </div>
        </div>
      </HeroShell>

      <ProgramContent program={program} />
    </div>
  )
}

/* ── Approved partner ────────────────────────────────────────────────────── */

type ApprovedProps = Props & { partner: PartnerSummary; stats: NonNullable<Props['stats']> }

function ApprovedView(props: ApprovedProps) {
  const [tab, setTab] = useState<TabId>(initialTab)

  const select = (id: TabId) => {
    setTab(id)
    try {
      const url = new URL(window.location.href)
      url.searchParams.set('tab', id)
      window.history.replaceState(window.history.state, '', url)
    } catch { /* the tab still switches */ }
  }

  return (
    <div>
      <div role='tablist' aria-label='Refer & earn sections' className='mb-6 flex gap-1 overflow-x-auto border-b'>
        {TABS.map((t) => (
          <button
            key={t.id}
            role='tab'
            type='button'
            aria-selected={tab === t.id}
            onClick={() => select(t.id)}
            className={cn(
              '-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
              tab === t.id ? 'border-orange-600 text-orange-700 dark:border-orange-400 dark:text-orange-300' : 'border-transparent text-muted-foreground hover:text-foreground'
            )}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'refer' && <ReferTab {...props} goEarnings={() => select('earnings')} />}
      {tab === 'earnings' && <EarningsTab {...props} />}
      {tab === 'program' && <ProgramContent program={props.program} partner={props.partner} />}
    </div>
  )
}

function HeroShell({ children }: { children: React.ReactNode }) {
  return <div className='rounded-3xl border bg-card px-5 py-10 text-card-foreground sm:px-10 sm:py-14'>{children}</div>
}

function useCopy() {
  const [copied, setCopied] = useState<string | null>(null)
  const copy = async (key: string, value: string) => {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(key)
      toast.success('Copied')
      setTimeout(() => setCopied(null), 1600)
    } catch {
      toast.error('Could not copy. Select it and copy by hand.')
    }
  }
  return { copied, copy }
}

function ReferTab({ partner, stats, program, goEarnings }: ApprovedProps & { goEarnings: () => void }) {
  const [mode, setMode] = useState<'link' | 'code'>('link')
  const { copied, copy } = useCopy()
  const value = mode === 'link' ? partner.referral_url : partner.code
  const topMultiplier = Math.max(...program.tiers.map((t) => t.multiplier))
  const shareText = `Running a Shopify store in Saudi Arabia? KSA Drop stores, packs, delivers and collects COD for you. Open your account here: ${partner.referral_url}`

  return (
    <div className='space-y-8'>
      <HeroShell>
        <div className='mx-auto max-w-2xl text-center'>
          <div className='mx-auto mb-6 inline-flex rounded-full bg-muted p-1' role='tablist' aria-label='Share as'>
            {([['link', 'Referral link', Link2], ['code', 'Referral code', Ticket]] as const).map(([id, label, Icon]) => (
              <button
                key={id}
                type='button'
                role='tab'
                aria-selected={mode === id}
                onClick={() => setMode(id)}
                className={cn(
                  'inline-flex items-center gap-2 rounded-full px-5 py-2 text-sm font-medium transition-colors',
                  mode === id ? 'bg-orange-600 text-white shadow' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                <Icon className='h-4 w-4' /> {label}
              </button>
            ))}
          </div>

          <h2 className='text-balance text-3xl font-semibold tracking-tight sm:text-4xl'>
            Earn up to <span className='tabular-nums'>{formatPoints(program.activation_points)}</span> points per referral
          </h2>
          <p className='mx-auto mt-3 max-w-xl text-balance text-sm text-muted-foreground sm:text-base'>
            Plus {formatPoints(program.delivery_points)} point on every delivery for {program.delivery_months} months, up to {topMultiplier.toFixed(2)}× at Gold tier.
          </p>

          <div className='mx-auto mt-7 flex max-w-xl items-center gap-2 rounded-2xl border bg-muted/50 p-2'>
            <code className={cn('min-w-0 flex-1 truncate px-3 text-left text-sm sm:text-base', mode === 'code' && 'font-mono font-semibold tracking-widest')}>
              {value}
            </code>
            <Button type='button' onClick={() => copy(mode, value)} className='shrink-0 bg-orange-600 text-white hover:bg-orange-700'>
              {copied === mode ? <Check className='h-4 w-4' /> : <Copy className='h-4 w-4' />} {copied === mode ? 'Copied' : 'Copy'}
            </Button>
          </div>

          <div className='mt-4 flex flex-wrap items-center justify-center gap-2 text-sm'>
            <a
              href={`https://wa.me/?text=${encodeURIComponent(shareText)}`}
              target='_blank'
              rel='noreferrer'
              className='inline-flex items-center gap-1.5 rounded-full border bg-background px-3.5 py-1.5 transition hover:bg-muted'
            >
              <MessageCircle className='h-4 w-4' /> WhatsApp
            </a>
            <a
              href={`mailto:?subject=${encodeURIComponent('Fulfillment for your Shopify store')}&body=${encodeURIComponent(shareText)}`}
              className='inline-flex items-center gap-1.5 rounded-full border bg-background px-3.5 py-1.5 transition hover:bg-muted'
            >
              <Mail className='h-4 w-4' /> Email
            </a>
            <Link href='/partners/assets' className='inline-flex items-center gap-1.5 rounded-full border bg-background px-3.5 py-1.5 transition hover:bg-muted'>
              <Megaphone className='h-4 w-4' /> Marketing assets
            </Link>
          </div>
        </div>
      </HeroShell>

      <div className='grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
        <Stat icon={Coins} label='Available points' value={formatPoints(stats.available_points)} hint={`Redeem from ${formatPoints(stats.min_redeem_points)}`} onClick={goEarnings} />
        <Stat icon={Users} label='Referred sellers' value={formatPoints(stats.referred_clients)} hint={`${stats.leads} lead${stats.leads === 1 ? '' : 's'} waiting`} />
        <Stat icon={PackageCheck} label='Active sellers' value={formatPoints(stats.active_clients)} hint='Shipped a delivered order' />
        <Stat icon={TrendingUp} label='Earned this month' value={formatPoints(stats.points_this_month)} hint={`${TIER_LABEL[partner.tier]} · ${stats.multiplier.toFixed(2)}× per delivery`} />
      </div>

      {stats.next_tier && (
        <Card className='gap-3 p-5'>
          <div className='flex flex-wrap items-center justify-between gap-2 text-sm'>
            <span className='font-medium'>Next tier: {stats.next_tier.name}</span>
            <span className='text-muted-foreground'>
              {Math.max(0, stats.next_tier.at - stats.active_clients)} more active seller{stats.next_tier.at - stats.active_clients === 1 ? '' : 's'} to go
            </span>
          </div>
          <div className='h-2 overflow-hidden rounded-full bg-muted'>
            <div className='h-full rounded-full bg-orange-500' style={{ width: `${Math.min(100, Math.round((stats.active_clients / stats.next_tier.at) * 100))}%` }} />
          </div>
        </Card>
      )}

      <section>
        <h3 className='mb-4 text-lg font-semibold'>How it works</h3>
        <div className='grid gap-4 md:grid-cols-3'>
          {[
            { n: 1, title: 'Share your link', body: 'Send your link or code to other store owners, in a group, a message or your content.' },
            { n: 2, title: 'They join KSA Drop', body: 'They open an account through your link and our team credits them to you.' },
            { n: 3, title: 'You earn points', body: `${formatPoints(program.activation_points)} points on their first delivered order, then every delivery after.` },
          ].map((s) => (
            <Card key={s.n} className='gap-2 p-5'>
              <span className='flex h-8 w-8 items-center justify-center rounded-full bg-orange-500/15 text-sm font-semibold text-orange-700 dark:text-orange-300'>{s.n}</span>
              <div className='font-medium'>{s.title}</div>
              <p className='text-sm text-muted-foreground'>{s.body}</p>
            </Card>
          ))}
        </div>
      </section>
    </div>
  )
}

function Stat({ icon: Icon, label, value, hint, onClick }: { icon: typeof Coins; label: string; value: string; hint?: string; onClick?: () => void }) {
  const body = (
    <>
      <div className='flex items-center gap-2 text-sm text-muted-foreground'>
        <span className='flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-300'><Icon className='h-4 w-4' /></span>
        {label}
      </div>
      <div className='mt-3 text-2xl font-semibold tabular-nums'>{value}</div>
      {hint && <div className='mt-1 text-xs text-muted-foreground'>{hint}</div>}
    </>
  )
  const cls = 'rounded-2xl border bg-card p-5 text-left shadow-xs'
  return onClick
    ? <button type='button' onClick={onClick} className={cn(cls, 'transition hover:border-orange-500/50')}>{body}</button>
    : <div className={cls}>{body}</div>
}

/* ── Earnings ────────────────────────────────────────────────────────────── */

function EarningsTab({ partner, stats, hasPayoutMethod, clients = [], payouts = [], canManage }: ApprovedProps) {
  const [sub, setSub] = useState<'clients' | 'payouts'>('clients')
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(0)
  const [busy, setBusy] = useState(false)
  const size = 5

  const min = stats.min_redeem_points
  const pct = Math.min(100, Math.round((stats.available_points / Math.max(1, min)) * 100))
  const reached = stats.available_points >= min
  const openRequest = payouts.some((p) => p.status === 'requested' || p.status === 'approved')

  const filteredClients = useMemo(
    () => clients.filter((c) => c.name.toLowerCase().includes(query.trim().toLowerCase())),
    [clients, query]
  )
  const rows = sub === 'clients' ? filteredClients : payouts
  const pages = Math.max(1, Math.ceil(rows.length / size))
  const current = Math.min(page, pages - 1)
  const slice = rows.slice(current * size, current * size + size)

  const requestPayout = () => {
    router.post('/partners/payouts', { points: stats.available_points }, {
      preserveScroll: true,
      onStart: () => setBusy(true),
      onFinish: () => setBusy(false),
    })
  }

  return (
    <div className='space-y-6'>
      <Card className='gap-5 p-6'>
        <div className='flex flex-wrap items-start justify-between gap-3'>
          <div>
            <h2 className='text-lg font-semibold'>Payout availability</h2>
            <p className='text-sm text-muted-foreground'>Total paid out: {formatPoints(stats.total_paid_points)} points</p>
          </div>
          <Button onClick={requestPayout} disabled={!canManage || !reached || !hasPayoutMethod || openRequest || busy}>
            <Wallet className='h-4 w-4' /> {busy ? 'Sending…' : openRequest ? 'Request in progress' : 'Request payout'}
          </Button>
        </div>

        {!hasPayoutMethod && (
          <div className='flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4'>
            <div className='flex items-start gap-3'>
              <AlertTriangle className='mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400' />
              <div>
                <div className='font-medium'>Missing payout method</div>
                <div className='text-sm text-muted-foreground'>Your earnings can&apos;t be paid out until you choose how to get paid.</div>
              </div>
            </div>
            <Button asChild variant='outline' size='sm'><Link href='/partners/profile'>Add payout method</Link></Button>
          </div>
        )}

        <div>
          <div className='relative h-3 overflow-hidden rounded-full bg-muted' role='progressbar' aria-valuemin={0} aria-valuemax={min} aria-valuenow={Math.min(stats.available_points, min)} aria-label='Points towards the minimum payout'>
            <div className='h-full rounded-full bg-orange-500 transition-all' style={{ width: `${pct}%` }} />
          </div>
          <div className='mt-2 flex justify-between text-xs text-muted-foreground'>
            <span><strong className='text-foreground tabular-nums'>{formatPoints(stats.available_points)}</strong> available points</span>
            <span>{reached ? 'Minimum reached' : `${formatPoints(min - stats.available_points)} to go`} · minimum {formatPoints(min)}</span>
          </div>
        </div>
        <p className='text-xs text-muted-foreground'>One point is valued at one SAR. Our team transfers by hand and marks each request paid.</p>
      </Card>

      <Card className='gap-0 p-0'>
        <div className='flex gap-1 border-b px-4'>
          {([['clients', 'Sellers', clients.length], ['payouts', 'Payouts', payouts.length]] as const).map(([id, label, n]) => (
            <button
              key={id}
              type='button'
              onClick={() => { setSub(id); setPage(0) }}
              className={cn(
                '-mb-px border-b-2 px-3 py-3 text-sm font-medium transition-colors',
                sub === id ? 'border-orange-600 text-orange-700 dark:border-orange-400 dark:text-orange-300' : 'border-transparent text-muted-foreground hover:text-foreground'
              )}
            >
              {label} <span className='ml-1 text-xs text-muted-foreground'>{n}</span>
            </button>
          ))}
        </div>

        <div className='space-y-3 p-4'>
          {sub === 'clients' && (
            <div className='relative'>
              <SearchIcon className='pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground' />
              <Input value={query} onChange={(e) => { setQuery(e.target.value); setPage(0) }} placeholder='Search sellers' className='pl-9' aria-label='Search sellers' />
            </div>
          )}

          {slice.length === 0 ? (
            <p className='py-10 text-center text-sm text-muted-foreground'>
              {sub === 'clients'
                ? clients.length === 0 ? 'No sellers yet. Share your link to get started.' : 'No seller matches your search.'
                : 'No payout requests yet.'}
            </p>
          ) : (
            <ul className='space-y-2'>
              {sub === 'clients'
                ? (slice as ReferredClient[]).map((c) => (
                    <li key={c.id} className='flex items-center gap-3 rounded-xl border p-3'>
                      <span className='flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-500/15 text-sm font-semibold text-orange-700 dark:text-orange-300'>
                        {c.name.trim().charAt(0).toUpperCase() || '?'}
                      </span>
                      <div className='min-w-0 flex-1'>
                        <div className='truncate font-medium'>{c.name}</div>
                        <div className='truncate text-xs text-muted-foreground'>
                          {c.activated && c.earning_until ? `Earning until ${formatDate(c.earning_until)}` : 'Waiting for first delivered order'}
                        </div>
                      </div>
                      <div className='flex shrink-0 flex-col items-end gap-1'>
                        <StatusBadge status={c.activated ? 'active' : 'pending'} />
                        <span className='text-xs text-muted-foreground'>{formatDate(c.referred_at)}</span>
                      </div>
                    </li>
                  ))
                : (slice as PayoutRow[]).map((p) => (
                    <li key={p.id} className='flex items-center gap-3 rounded-xl border p-3'>
                      <span className='flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'><Wallet className='h-4 w-4' /></span>
                      <div className='min-w-0 flex-1'>
                        <div className='font-medium tabular-nums'>{formatPoints(p.points)} points</div>
                        <div className='truncate text-xs text-muted-foreground'>
                          {p.status === 'paid' && p.amount_paid != null ? `${Number(p.amount_paid).toFixed(2)} ${p.currency} · paid ${formatDate(p.paid_at)}` : `Requested ${formatDate(p.created_at)}`}
                        </div>
                      </div>
                      <StatusBadge status={p.status} />
                    </li>
                  ))}
            </ul>
          )}

          <div className='flex flex-wrap items-center justify-between gap-2 pt-1 text-sm text-muted-foreground'>
            <span>{rows.length === 0 ? '0 results' : `${current * size + 1} to ${Math.min(rows.length, current * size + size)} of ${rows.length}`}</span>
            <div className='flex items-center gap-1'>
              <Button type='button' variant='ghost' size='icon' aria-label='Previous page' disabled={current === 0} onClick={() => setPage(current - 1)}><ChevronLeft className='h-4 w-4' /></Button>
              <span className='px-1'>Page {current + 1} of {pages}</span>
              <Button type='button' variant='ghost' size='icon' aria-label='Next page' disabled={current >= pages - 1} onClick={() => setPage(current + 1)}><ChevronRight className='h-4 w-4' /></Button>
            </div>
          </div>
        </div>
      </Card>

      <p className='text-xs text-muted-foreground'>
        Full points history lives on the <Link href='/partners/points' className='underline underline-offset-2'>Points page</Link>. {partner.display_name}&apos;s payout details are managed in the <Link href='/partners/profile' className='underline underline-offset-2'>partner profile</Link>.
      </p>
    </div>
  )
}

/* ── Program (benefits) ──────────────────────────────────────────────────── */

function ProgramContent({ program, partner }: { program: ProgramSummary; partner?: PartnerSummary }) {
  const benefits = [
    { icon: Gift, title: 'Welcome bonus', body: `${formatPoints(program.approval_bonus_points)} points the day your application is approved.` },
    { icon: Sparkles, title: 'Referral activated', body: `${formatPoints(program.activation_points)} points when a seller you refer ships their first delivered order.` },
    { icon: PackageCheck, title: 'Recurring on deliveries', body: `${formatPoints(program.delivery_points)} point × your tier on every delivery, for ${program.delivery_months} months after their first one.` },
    { icon: TrendingUp, title: 'Milestone bonus', body: `${formatPoints(program.milestone_points)} points when a referred seller reaches ${formatPoints(program.milestone_deliveries)} delivered shipments.` },
    { icon: Handshake, title: 'Tiers', body: 'Silver and Gold multiply your per-delivery points as your active sellers grow.' },
    { icon: Clock, title: 'Simple payouts', body: `Redeem from ${formatPoints(program.min_redeem_points)} points. One point is valued at one SAR.` },
  ]

  return (
    <div className='space-y-8'>
      <section>
        <h3 className='mb-4 text-lg font-semibold'>{partner ? 'Your partner benefits' : 'What you get'}</h3>
        <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>
          {benefits.map(({ icon: Icon, title, body }) => (
            <Card key={title} className='gap-3 p-5'>
              <span className='flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-300'><Icon className='h-5 w-5' /></span>
              <div className='font-medium'>{title}</div>
              <p className='text-sm text-muted-foreground'>{body}</p>
            </Card>
          ))}
        </div>
      </section>

      <section>
        <h3 className='mb-4 text-lg font-semibold'>Points schedule</h3>
        <ProgramTable program={program} />
      </section>

      <section>
        <h3 className='mb-4 text-lg font-semibold'>Tiers</h3>
        <TierTable program={program} />
      </section>
    </div>
  )
}
