import { Link } from '@inertiajs/react'
import { Coins, Users, UserCheck, PackageCheck, Sparkles, Inbox } from 'lucide-react'
import { StatCard } from '@/components/stat-card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { PartnerShell } from '@/features/partners/partner-shell'
import { ProgramTable } from '@/features/partners/program-table'
import type { PartnerSummary, PointTransaction, ProgramSummary } from '@/features/partners/types'

// One 3D coin (Icons8, 3D Enterprise), tinted per level; the style has no gold or bronze version.
const TIER_COIN_FILTER: Record<string, string> = {
  bronze: 'sepia(1) saturate(3) hue-rotate(-18deg) brightness(0.72)',
  silver: 'none',
  gold: 'sepia(1) saturate(4.5) hue-rotate(-8deg) brightness(1.08)',
}

type Props = {
  partner: PartnerSummary
  stats: {
    points_balance: number
    available_points: number
    referred_clients: number
    active_clients: number
    leads: number
    deliveries_this_month: number
    points_this_month: number
    tier: string
    multiplier: number
    next_tier: { name: string; at: number } | null
    min_redeem_points: number
  }
  recent: PointTransaction[]
  program: ProgramSummary
}

export default function Dashboard({ partner, stats, recent, program }: Props) {
  const toNext = stats.next_tier ? Math.min(100, Math.round((stats.active_clients / stats.next_tier.at) * 100)) : 100
  const canRedeem = stats.available_points >= stats.min_redeem_points

  return (
    <PartnerShell
      title={`Welcome, ${partner.display_name}`}
      description='Your referral link, your points and what your referred sellers are doing.'
      partner={partner}
      actions={
        <Button asChild size='sm' disabled={!canRedeem}>
          <Link href='/partners/payouts'>Redeem points</Link>
        </Button>
      }
    >
      <div className='grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6'>
        <StatCard icon={<Coins className='h-4 w-4' />} label='Points balance' value={stats.points_balance} tone='orange' hint='Points you hold. Redeem from the Payouts page.' />
        <StatCard icon={<Sparkles className='h-4 w-4' />} label='Earned this month' value={stats.points_this_month} tone='teal' texture='dots' />
        <StatCard icon={<Users className='h-4 w-4' />} label='Referred sellers' value={stats.referred_clients} tone='blue' texture='grid' hint='Accounts our team credited to you.' />
        <StatCard icon={<UserCheck className='h-4 w-4' />} label='Active sellers' value={stats.active_clients} tone='emerald' hint='Referred sellers with at least one delivered order. These set your tier.' />
        <StatCard icon={<PackageCheck className='h-4 w-4' />} label='Deliveries this month' value={stats.deliveries_this_month} tone='violet' texture='dots' />
        <StatCard icon={<Inbox className='h-4 w-4' />} label='Leads sent' value={stats.leads} tone='rose' texture='grid' hint='Sellers who filled the form on your referral page.' href='/partners/referrals' />
      </div>

      <div className='mt-6 grid gap-6 lg:grid-cols-[1fr_1fr]'>
        <Card>
          <CardHeader>
            <CardTitle className='capitalize'>{stats.tier} tier · {stats.multiplier.toFixed(2)}× on deliveries</CardTitle>
            <CardDescription>
              {stats.next_tier
                ? `${stats.next_tier.at - stats.active_clients > 0 ? stats.next_tier.at - stats.active_clients : 0} more active seller${stats.next_tier.at - stats.active_clients === 1 ? '' : 's'} to reach ${stats.next_tier.name}.`
                : 'You are at the top tier.'}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Progress value={toNext} />
            <div className='mt-2 flex justify-between text-xs text-muted-foreground'>
              <span>{stats.active_clients} active</span>
              {stats.next_tier && <span>{stats.next_tier.at} for {stats.next_tier.name}</span>}
            </div>
            <div className='mt-6'>
              <ProgramTable program={program} compact />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Your path to the top</CardTitle>
            <CardDescription>Active sellers move you up. Each level pays more on every delivery.</CardDescription>
          </CardHeader>
          <CardContent>
            <ol className='relative'>
              {program.tiers.map((tier, i) => {
                const reached = stats.active_clients >= tier.min_active_clients && (i > 0 || stats.active_clients > 0 || stats.tier === tier.name.toLowerCase())
                const current = tier.name.toLowerCase() === stats.tier
                const last = i === program.tiers.length - 1
                const next = program.tiers[i + 1]
                const span = next ? next.min_active_clients - tier.min_active_clients : 0
                const done = next ? Math.min(100, Math.max(0, Math.round(((stats.active_clients - tier.min_active_clients) / span) * 100))) : 0
                return (
                  <li key={tier.name} className='relative flex gap-4 pb-8 last:pb-0'>
                    {!last && (
                      <span aria-hidden className='absolute start-[21px] top-11 h-[calc(100%-2.75rem)] w-0.5 overflow-hidden rounded bg-muted'>
                        <span className='block w-full bg-emerald-500 transition-all duration-700' style={{ height: `${done}%` }} />
                      </span>
                    )}
                    <span
                      className={`relative z-10 grid h-11 w-11 shrink-0 place-items-center rounded-full ${
                        current ? 'ring-4 ring-emerald-500/30' : ''
                      }`}
                    >
                      <img
                        src='/images/tiers/coin.png'
                        alt=''
                        width={36}
                        height={36}
                        className={`h-9 w-9 object-contain drop-shadow-md ${reached ? '' : 'opacity-40 grayscale'}`}
                        style={reached ? { filter: TIER_COIN_FILTER[tier.name.toLowerCase()] } : undefined}
                      />
                    </span>
                    <div className='min-w-0 flex-1 pt-0.5'>
                      <div className='flex flex-wrap items-center gap-2'>
                        <span className='font-semibold capitalize'>{tier.name}</span>
                        {current && <Badge className='bg-emerald-600 hover:bg-emerald-600'>You are here</Badge>}
                        <span className='ms-auto text-sm font-semibold tabular-nums'>{tier.multiplier.toFixed(2)}×</span>
                      </div>
                      <p className='text-sm text-muted-foreground'>
                        {i === 0 ? 'Starts with your first active seller' : `Reach ${tier.min_active_clients} active sellers`}
                        {' · '}+{Math.round(program.delivery_points * tier.multiplier * 100) / 100} pts per delivery
                      </p>
                      {current && next && (
                        <p className='mt-1 text-xs text-emerald-700 dark:text-emerald-400'>
                          {Math.max(0, next.min_active_clients - stats.active_clients)} more active seller{next.min_active_clients - stats.active_clients === 1 ? '' : 's'} to unlock {next.name}
                        </p>
                      )}
                      {current && !next && <p className='mt-1 text-xs text-emerald-700 dark:text-emerald-400'>Top level reached.</p>}
                    </div>
                  </li>
                )
              })}
            </ol>
          </CardContent>
        </Card>
      </div>
    </PartnerShell>
  )
}
