import { useState } from 'react'
import { Link, useForm } from '@inertiajs/react'
import { Landmark, Pencil, Plus, RotateCw, Wallet } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { PartnerShell, StatusBadge } from '@/features/partners/partner-shell'
import { formatDate, formatPoints, type PartnerSummary, type PayoutMethod, type PayoutRequestRow } from '@/features/partners/types'

type Props = {
  partner: PartnerSummary
  available: number
  minRedeem: number
  hasPayoutMethod: boolean
  payoutMethod: PayoutMethod
  requests: PayoutRequestRow[]
}

export default function Payouts({ partner, available, minRedeem, hasPayoutMethod, payoutMethod, requests }: Props) {
  const open = requests.some((r) => r.status === 'requested' || r.status === 'approved')
  const { data, setData, post, processing, errors } = useForm({ points: available >= minRedeem ? available : minRedeem, note: '' })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post('/partners/payouts', { preserveScroll: true })
  }

  const blocked = partner.status === 'pending' ? 'Payouts open once your application is approved.'
    : !hasPayoutMethod ? 'Add a payout method to your profile first.'
    : open ? 'You already have a payout request in progress.'
      : available < minRedeem ? `You need at least ${formatPoints(minRedeem)} available points. You have ${formatPoints(available)}.`
        : null

  return (
    <PartnerShell title='Payouts' description='Redeem points. Our team transfers by hand and marks the request paid.' partner={partner}>
      <div className='grid gap-6 lg:grid-cols-[360px_1fr]'>
        <div className='space-y-6'>
        <PayoutMethodCard method={payoutMethod} holder={partner.display_name} />
        <Card>
          <CardHeader>
            <CardTitle>Request a payout</CardTitle>
            <CardDescription>
              Available: <strong>{formatPoints(available)}</strong> points · minimum {formatPoints(minRedeem)}. One point is valued at one SAR.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {blocked ? (
              <div className='space-y-3 text-sm text-muted-foreground'>
                <p>{blocked}</p>
                {!hasPayoutMethod && (
                  <Button asChild variant='outline' size='sm'><Link href='/partners/profile'>Add payout method</Link></Button>
                )}
              </div>
            ) : (
              <form onSubmit={submit} className='space-y-4'>
                <div className='grid gap-1.5'>
                  <Label htmlFor='po-points'>Points to redeem</Label>
                  <Input id='po-points' type='number' min={minRedeem} max={available} value={data.points} onChange={(e) => setData('points', Number(e.target.value))} />
                  {errors.points && <p className='text-xs text-destructive'>{errors.points}</p>}
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='po-note'>Note for our team</Label>
                  <Textarea id='po-note' rows={2} value={data.note} onChange={(e) => setData('note', e.target.value)} />
                </div>
                <Button type='submit' disabled={processing}>{processing ? 'Sending…' : 'Request payout'}</Button>
              </form>
            )}
          </CardContent>
        </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>History</CardTitle>
            <CardDescription>Points leave your balance when a request is approved and return if it is rejected.</CardDescription>
          </CardHeader>
          <CardContent>
            {requests.length === 0 ? (
              <p className='py-8 text-center text-sm text-muted-foreground'>No payout requests yet.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Requested</TableHead>
                    <TableHead className='text-right'>Points</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Paid</TableHead>
                    <TableHead>Note</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {requests.map((r) => (
                    <TableRow key={r.id}>
                      <TableCell className='whitespace-nowrap'>{formatDate(r.created_at)}</TableCell>
                      <TableCell className='text-right tabular-nums'>{formatPoints(r.points)}</TableCell>
                      <TableCell><StatusBadge status={r.status} /></TableCell>
                      <TableCell className='whitespace-nowrap'>
                        {r.status === 'paid' && r.amount_paid != null ? `${Number(r.amount_paid).toFixed(2)} ${r.currency} · ${formatDate(r.paid_at)}` : '—'}
                      </TableCell>
                      <TableCell className='max-w-[240px] truncate text-muted-foreground'>{r.admin_note ?? r.partner_note ?? '—'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </PartnerShell>
  )
}

/** "SA03 8000 0000 6080 1016 7519" -> "SA•• •••• •••• •••• 7519". */
const maskIban = (iban: string) => {
  const clean = iban.replace(/\s+/g, '')
  if (clean.length <= 8) return clean
  const masked = clean.slice(0, 2) + '•'.repeat(clean.length - 6) + clean.slice(-4)
  return masked.replace(/(.{4})/g, '$1 ').trim()
}

const spaced = (iban: string) => iban.replace(/\s+/g, '').replace(/(.{4})/g, '$1 ').trim()

/**
 * The payout method as a card that flips: the front keeps the account
 * number hidden, the back shows everything the team will pay into.
 */
function PayoutMethodCard({ method, holder }: { method: PayoutMethod; holder: string }) {
  const [flipped, setFlipped] = useState(false)

  if (!method) {
    return (
      <Link
        href='/partners/profile'
        className='group flex h-48 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed text-sm text-muted-foreground transition hover:border-orange-500/60 hover:text-foreground'
      >
        <span className='grid h-10 w-10 place-items-center rounded-full bg-muted transition-transform group-hover:scale-110'>
          <Plus className='h-5 w-5' />
        </span>
        Add a payout method
      </Link>
    )
  }

  const isBank = method.type === 'bank'
  const face = 'absolute inset-0 flex flex-col justify-between overflow-hidden rounded-2xl p-5 text-white shadow-lg [backface-visibility:hidden]'

  return (
    <div>
      <button
        type='button'
        onClick={() => setFlipped((f) => !f)}
        aria-pressed={flipped}
        aria-label={flipped ? 'Hide payout method details' : 'Show payout method details'}
        className='block h-48 w-full text-start [perspective:1200px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-2xl'
      >
        <span
          className='relative block h-full w-full transition-transform duration-700 ease-out [transform-style:preserve-3d] motion-reduce:duration-0'
          style={{ transform: flipped ? 'rotateY(180deg)' : 'rotateY(0deg)' }}
        >
          {/* Front */}
          <span className={`${face} bg-gradient-to-br from-slate-800 via-slate-900 to-black`} aria-hidden={flipped}>
            <span aria-hidden className='pointer-events-none absolute -end-12 -top-12 h-40 w-40 rounded-full bg-orange-500/20' />
            <span aria-hidden className='pointer-events-none absolute -bottom-16 start-8 h-36 w-36 rounded-full bg-white/5' />
            <span className='relative flex items-center justify-between'>
              <span className='flex items-center gap-2 text-xs font-medium uppercase tracking-widest text-white/70'>
                {isBank ? <Landmark className='h-4 w-4' /> : <Wallet className='h-4 w-4' />}
                {isBank ? method.bank_name : 'Payout method'}
              </span>
              <span className='h-7 w-10 rounded-md bg-gradient-to-br from-amber-300 to-amber-500' />
            </span>
            <span className='relative font-mono text-lg tracking-widest'>
              {isBank ? maskIban(method.iban ?? '') : 'Custom method'}
            </span>
            <span className='relative flex items-end justify-between text-xs'>
              <span>
                <span className='block text-[10px] uppercase tracking-widest text-white/60'>{isBank ? 'Account holder' : 'Partner'}</span>
                <span className='font-medium uppercase'>{isBank ? method.holder_name : holder}</span>
              </span>
              <span className='flex items-center gap-1 text-white/70'><RotateCw className='h-3 w-3' /> Tap to flip</span>
            </span>
          </span>

          {/* Back */}
          <span
            className={`${face} bg-gradient-to-br from-orange-600 via-orange-700 to-amber-800`}
            style={{ transform: 'rotateY(180deg)' }}
            aria-hidden={!flipped}
          >
            <span aria-hidden className='pointer-events-none absolute -start-10 -top-10 h-32 w-32 rounded-full bg-white/10' />
            <span className='relative text-xs font-medium uppercase tracking-widest text-white/80'>
              {isBank ? 'Bank transfer details' : 'Payout details'}
            </span>
            {isBank ? (
              <dl className='relative space-y-2 text-sm'>
                <Detail label='Bank' value={method.bank_name} />
                <Detail label='Holder' value={method.holder_name} />
                <Detail label='IBAN' value={spaced(method.iban ?? '')} mono />
              </dl>
            ) : (
              <p className='relative break-words text-sm'>{method.details}</p>
            )}
            <span className='relative flex items-center gap-1 text-xs text-white/75'><RotateCw className='h-3 w-3' /> Tap to flip back</span>
          </span>
        </span>
      </button>
      <div className='mt-2 flex items-center justify-between text-xs text-muted-foreground'>
        <span>Our team pays into this account.</span>
        <Link href='/partners/profile' className='inline-flex items-center gap-1 hover:text-foreground'>
          <Pencil className='h-3 w-3' /> Edit
        </Link>
      </div>
    </div>
  )
}

function Detail({ label, value, mono }: { label: string; value?: string | null; mono?: boolean }) {
  return (
    <div>
      <dt className='text-[10px] uppercase tracking-widest text-white/65'>{label}</dt>
      <dd className={mono ? 'font-mono tracking-wider' : 'font-medium'}>{value || '—'}</dd>
    </div>
  )
}
