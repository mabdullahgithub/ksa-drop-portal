import { useEffect, useState } from 'react'
import { Link } from '@inertiajs/react'
import {
  ArrowDownRight, ArrowRight, ArrowUpRight, Award, BookOpen, Coins, Gift, ListOrdered, LockKeyhole, PackageCheck,
  Sparkles, TrendingUp, UserCheck, Wallet,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import Antigravity from '@/components/antigravity/Antigravity'
import { POINTS_AURORA, PartnerShell } from '@/features/partners/partner-shell'
import { ProgramTable } from '@/features/partners/program-table'
import {
  TIER_LABEL, TRANSACTION_LABEL, formatDate, formatPoints, type PartnerSummary, type PartnerTier, type PointTransaction,
  type ProgramSummary,
} from '@/features/partners/types'

type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  prev_page_url: string | null
  next_page_url: string | null
  total: number
}

type Stats = {
  balance: number
  available: number
  on_hold: number
  lifetime_earned: number
  redeemed: number
  this_month: number
  last_month: number
  min_redeem: number
  tier: PartnerTier
  multiplier: number
  active_clients: number
  next_tier: { name: string; at: number } | null
  monthly: { label: string; points: number }[]
  breakdown: { type: string; points: number; entries: number }[]
}

type Props = {
  partner: PartnerSummary
  transactions: Paginated<PointTransaction>
  stats: Stats
  program: ProgramSummary
}

const TIER_DOT: Record<PartnerTier, string> = {
  bronze: 'bg-orange-700',
  silver: 'bg-slate-400',
  gold: 'bg-yellow-500',
}

const SOURCE_COLOR: Record<string, string> = {
  delivery: 'bg-emerald-500',
  activation: 'bg-blue-500',
  milestone: 'bg-violet-500',
  approval_bonus: 'bg-amber-500',
  adjustment: 'bg-slate-400',
}

export default function Points({ partner, transactions, stats, program }: Props) {
  const approved = partner.status === 'approved'
  const redeemPct = Math.min(100, Math.round((stats.available / Math.max(1, stats.min_redeem)) * 100))
  const canRedeem = approved && stats.available >= stats.min_redeem
  const maxMonth = Math.max(1, ...stats.monthly.map((m) => m.points))
  const totalEarned = stats.breakdown.reduce((sum, b) => sum + b.points, 0)
  const change = stats.last_month > 0 ? Math.round(((stats.this_month - stats.last_month) / stats.last_month) * 100) : null

  return (
    <PartnerShell title='Points' description='Your balance, where it came from and how to earn more.' partner={partner}>
      <Tabs defaultValue='overview' className='gap-6'>
        <TabsList>
          <TabsTrigger value='overview'><Coins className='h-4 w-4' /> Overview</TabsTrigger>
          <TabsTrigger value='ledger'><ListOrdered className='h-4 w-4' /> Ledger</TabsTrigger>
          <TabsTrigger value='guide'><BookOpen className='h-4 w-4' /> Points guide</TabsTrigger>
        </TabsList>

        {/* ---------- Overview ---------- */}
        <TabsContent value='overview' className='space-y-6'>
          <div className='grid gap-4 lg:grid-cols-[1.4fr_1fr]'>
            <Card style={POINTS_AURORA} className='overflow-hidden border-0 text-white ring-1 ring-white/10'>
              <CardContent className='relative pt-6'>
                <div aria-hidden className='absolute inset-0'>
                  <Antigravity
                    colors={['#ffffff', '#9fd6ff', '#fde68a']}
                    count={1200}
                    magnetRadius={9}
                    ringRadius={7}
                    particleSize={0.9}
                    autoAnimate
                    opacity={0.7}
                  />
                </div>
                <img
                  src='/images/points-piggy-bank.png'
                  alt=''
                  aria-hidden
                  className='pointer-events-none absolute -bottom-2 end-2 hidden h-44 w-auto select-none drop-shadow-xl sm:block'
                />
                <div className='relative flex items-start justify-between gap-4'>
                  <div>
                    <div className='flex items-center gap-2 text-sm font-medium text-white/85'>
                      <Coins className='h-4 w-4' /> Points balance
                    </div>
                    <div className='mt-2 text-5xl font-bold tabular-nums tracking-tight'>{formatPoints(stats.balance)}</div>
                    <div className='mt-1 text-sm text-white/80'>
                      {formatPoints(stats.available)} available to redeem
                      {stats.on_hold > 0 && <> · {formatPoints(stats.on_hold)} on hold in a payout request</>}
                    </div>
                  </div>
                  <Badge className='border-0 bg-white/20 text-white capitalize hover:bg-white/20'>
                    <span className={`mr-1.5 inline-block h-2 w-2 rounded-full ${TIER_DOT[stats.tier]}`} />
                    {TIER_LABEL[stats.tier]} · {stats.multiplier.toFixed(2)}×
                  </Badge>
                </div>

                <div className='relative mt-6 sm:pe-40'>
                  <div className='mb-1.5 flex justify-between text-xs text-white/85'>
                    <span>{canRedeem ? 'You can redeem now' : `${formatPoints(Math.max(0, stats.min_redeem - stats.available))} more points until you can redeem`}</span>
                    <span>Minimum {formatPoints(stats.min_redeem)}</span>
                  </div>
                  <RedeemProgress value={redeemPct} />
                </div>

                <div className='relative mt-5'>
                  <Button asChild size='sm' variant='secondary' disabled={!canRedeem}>
                    <Link href='/partners/payouts'>Redeem points <ArrowRight className='h-4 w-4' /></Link>
                  </Button>
                </div>
              </CardContent>
            </Card>

            <div className='grid grid-cols-2 gap-4'>
              <MiniStat icon={<TrendingUp className='h-4 w-4' />} label='Lifetime earned' value={formatPoints(stats.lifetime_earned)} />
              <MiniStat icon={<Wallet className='h-4 w-4' />} label='Redeemed' value={formatPoints(stats.redeemed)} />
              <MiniStat
                icon={<Sparkles className='h-4 w-4' />}
                label='Earned this month'
                value={formatPoints(stats.this_month)}
                trend={change}
              />
              <MiniStat icon={<LockKeyhole className='h-4 w-4' />} label='On hold' value={formatPoints(stats.on_hold)} />
            </div>
          </div>

          <div className='grid gap-6 lg:grid-cols-2'>
            <Card>
              <CardHeader>
                <CardTitle>Points earned, last 6 months</CardTitle>
                <CardDescription>Only points you earned. Redemptions are not counted.</CardDescription>
              </CardHeader>
              <CardContent>
                <div className='flex h-44 items-end gap-3' role='img' aria-label='Points earned per month'>
                  {stats.monthly.map((m, i) => (
                    <div key={`${m.label}-${i}`} className='flex flex-1 flex-col items-center justify-end gap-1.5'>
                      <span className='text-xs tabular-nums text-muted-foreground'>{m.points > 0 ? formatPoints(m.points) : ''}</span>
                      <div
                        className={`w-full rounded-t-md ${i === stats.monthly.length - 1 ? 'bg-orange-500' : 'bg-orange-500/45'}`}
                        style={{ height: `${Math.max(m.points > 0 ? 6 : 2, (m.points / maxMonth) * 100)}%` }}
                      />
                      <span className='text-xs text-muted-foreground'>{m.label}</span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Where your points come from</CardTitle>
                <CardDescription>Share of everything you have earned.</CardDescription>
              </CardHeader>
              <CardContent>
                {totalEarned === 0 ? (
                  <p className='py-10 text-center text-sm text-muted-foreground'>
                    Nothing earned yet. Check the Points guide to see how it works.
                  </p>
                ) : (
                  <>
                    <div className='flex h-3 overflow-hidden rounded-full bg-muted'>
                      {stats.breakdown.map((b) => (
                        <div key={b.type} className={SOURCE_COLOR[b.type] ?? 'bg-slate-400'} style={{ width: `${(b.points / totalEarned) * 100}%` }} />
                      ))}
                    </div>
                    <ul className='mt-4 space-y-2.5'>
                      {[...stats.breakdown].sort((a, b) => b.points - a.points).map((b) => (
                        <li key={b.type} className='flex items-center justify-between gap-3 text-sm'>
                          <span className='flex items-center gap-2'>
                            <span className={`h-2.5 w-2.5 rounded-full ${SOURCE_COLOR[b.type] ?? 'bg-slate-400'}`} />
                            {TRANSACTION_LABEL[b.type] ?? b.type}
                            <span className='text-xs text-muted-foreground'>×{b.entries}</span>
                          </span>
                          <span className='tabular-nums font-medium'>
                            {formatPoints(b.points)}
                            <span className='ml-1.5 text-xs text-muted-foreground'>{Math.round((b.points / totalEarned) * 100)}%</span>
                          </span>
                        </li>
                      ))}
                    </ul>
                  </>
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ---------- Ledger ---------- */}
        <TabsContent value='ledger'>
          <Card>
            <CardHeader>
              <CardTitle>Ledger</CardTitle>
              <CardDescription>{formatPoints(transactions.total)} entries. Balance: {formatPoints(partner.points_balance)} points.</CardDescription>
            </CardHeader>
            <CardContent>
              {transactions.data.length === 0 ? (
                <p className='py-8 text-center text-sm text-muted-foreground'>No points yet.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Date</TableHead>
                      <TableHead>Event</TableHead>
                      <TableHead>Details</TableHead>
                      <TableHead className='text-right'>Points</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {transactions.data.map((t) => (
                      <TableRow key={t.id}>
                        <TableCell className='whitespace-nowrap'>{formatDate(t.created_at)}</TableCell>
                        <TableCell className='font-medium'>{TRANSACTION_LABEL[t.type] ?? t.type}</TableCell>
                        <TableCell className='text-muted-foreground'>{t.description ?? '—'}</TableCell>
                        <TableCell className={`text-right tabular-nums font-semibold ${t.points < 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                          {t.points > 0 ? '+' : ''}{formatPoints(t.points)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
              {transactions.last_page > 1 && (
                <div className='mt-4 flex items-center justify-between text-sm text-muted-foreground'>
                  <span>Page {transactions.current_page} of {transactions.last_page}</span>
                  <div className='flex gap-2'>
                    <Button asChild variant='outline' size='sm' disabled={!transactions.prev_page_url}>
                      <Link href={transactions.prev_page_url ?? '#'} preserveScroll>Previous</Link>
                    </Button>
                    <Button asChild variant='outline' size='sm' disabled={!transactions.next_page_url}>
                      <Link href={transactions.next_page_url ?? '#'} preserveScroll>Next</Link>
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* ---------- Guide ---------- */}
        <TabsContent value='guide' className='space-y-6'>
          <Card>
            <CardHeader>
              <CardTitle>How points work</CardTitle>
              <CardDescription>
                You earn points when sellers you referred succeed with KSA Drop. When you have enough, redeem them for a payout.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <ol className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
                <Step n={1} icon={<Gift className='h-4 w-4' />} title='Share your link'>
                  Send your referral link or code to sellers. We credit them to you when they join.
                </Step>
                <Step n={2} icon={<UserCheck className='h-4 w-4' />} title='They get activated'>
                  Their first delivered order activates them and pays an activation bonus.
                </Step>
                <Step n={3} icon={<PackageCheck className='h-4 w-4' />} title='Deliveries add up'>
                  Every delivered shipment earns points for {program.delivery_months} months, multiplied by your tier.
                </Step>
                <Step n={4} icon={<Wallet className='h-4 w-4' />} title='Redeem'>
                  Once you hold {formatPoints(program.min_redeem_points)} points, request a payout from the Payouts page.
                </Step>
              </ol>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ways to earn</CardTitle>
              <CardDescription>The live schedule, straight from the program settings.</CardDescription>
            </CardHeader>
            <CardContent>
              <ProgramTable program={program} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Tiers</CardTitle>
              <CardDescription>Your tier follows your active sellers, those with at least one delivered order. A higher tier multiplies delivery points.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className='grid gap-4 sm:grid-cols-3'>
                {program.tiers.map((tier) => {
                  const key = tier.name.toLowerCase() as PartnerTier
                  const current = key === stats.tier
                  return (
                    <div key={tier.name} className={`rounded-2xl border p-4 ${current ? 'border-orange-500 bg-orange-500/5' : ''}`}>
                      <div className='flex items-center justify-between'>
                        <span className='flex items-center gap-2 font-semibold'>
                          <Award className='h-4 w-4' /> {tier.name}
                        </span>
                        {current && <Badge variant='secondary'>You are here</Badge>}
                      </div>
                      <div className='mt-3 text-3xl font-bold tabular-nums'>{tier.multiplier.toFixed(2)}×</div>
                      <div className='text-xs text-muted-foreground'>
                        on delivery points
                      </div>
                      <div className='mt-3 text-sm'>
                        {tier.min_active_clients === 0 ? 'Where everyone starts' : `${tier.min_active_clients}+ active sellers`}
                      </div>
                      <div className='mt-1 text-xs text-muted-foreground'>
                        Each delivery: {formatPoints(Math.round(program.delivery_points * tier.multiplier))} pts
                      </div>
                    </div>
                  )
                })}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Good to know</CardTitle>
            </CardHeader>
            <CardContent>
              <ul className='list-disc space-y-2 pl-5 text-sm text-muted-foreground'>
                <li>Each event pays once. If a courier update arrives twice you are not paid twice.</li>
                <li>Delivery points stop {program.delivery_months} months after a seller's first delivery. The milestone and activation bonuses are one-time.</li>
                <li>Points held by an open payout request show as “on hold” and cannot be redeemed again until it is decided.</li>
                <li>A rejected payout returns its points to your balance and shows in the ledger as “Payout reversed”.</li>
                <li>Our team may adjust points to correct a mistake. Every adjustment shows in your ledger with a note.</li>
                <li>Points are never shown for what a referred seller pays. Only your own points appear here.</li>
              </ul>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </PartnerShell>
  )
}

function MiniStat({ icon, label, value, trend }: { icon: React.ReactNode; label: string; value: string; trend?: number | null }) {
  return (
    <Card>
      <CardContent className='pt-5'>
        <div className='flex items-center gap-1.5 text-xs text-muted-foreground'>{icon} {label}</div>
        <div className='mt-1.5 text-2xl font-bold tabular-nums'>{value}</div>
        {trend != null && (
          <div className={`mt-1 flex items-center gap-0.5 text-xs ${trend >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
            {trend >= 0 ? <ArrowUpRight className='h-3 w-3' /> : <ArrowDownRight className='h-3 w-3' />}
            {Math.abs(trend)}% vs last month
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function Step({ n, icon, title, children }: { n: number; icon: React.ReactNode; title: string; children: React.ReactNode }) {
  return (
    <li className='rounded-2xl border p-4'>
      <div className='flex items-center gap-2'>
        <span className='flex h-7 w-7 items-center justify-center rounded-full bg-orange-500/15 text-orange-600'>{icon}</span>
        <span className='text-xs font-medium text-muted-foreground'>Step {n}</span>
      </div>
      <div className='mt-2 font-semibold'>{title}</div>
      <p className='mt-1 text-sm text-muted-foreground'>{children}</p>
    </li>
  )
}

/** Fills in from zero on load, with sliding stripes and a pulsing knob at the tip. */
function RedeemProgress({ value }: { value: number }) {
  const [shown, setShown] = useState(0)
  useEffect(() => {
    const id = requestAnimationFrame(() => setShown(value))
    return () => cancelAnimationFrame(id)
  }, [value])
  const done = value >= 100

  return (
    <div
      role='progressbar'
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={value}
      className='relative h-3.5 rounded-full bg-black/20 p-0.5 shadow-inner'
    >
      <div
        className='relative h-full min-w-2.5 rounded-full bg-gradient-to-r from-amber-300 via-yellow-200 to-white transition-[width] duration-1000 ease-out'
        style={{ width: `${shown}%` }}
      >
        <span
          aria-hidden
          className='progress-stripes absolute inset-0 rounded-full opacity-60'
          style={{
            backgroundImage: 'repeating-linear-gradient(45deg, rgb(255 255 255 / 0.55) 0 7px, transparent 7px 14px)',
            backgroundSize: '28px 28px',
            animation: 'progress-stripes 1s linear infinite',
          }}
        />
        <span
          aria-hidden
          className='progress-knob absolute -end-0.5 top-1/2 h-4 w-4 -translate-y-1/2 rounded-full bg-white shadow-md ring-2 ring-amber-300'
          style={done ? undefined : { animation: 'progress-knob 1.6s ease-out infinite' }}
        />
      </div>
    </div>
  )
}
