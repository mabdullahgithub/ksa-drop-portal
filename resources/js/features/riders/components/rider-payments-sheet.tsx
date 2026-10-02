import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { format } from 'date-fns'
import { Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { EmptyState } from '@/components/empty-state'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { usePermissions } from '@/hooks/use-permissions'
import { businessToday, toBusinessTime } from '@/lib/business-time'
import { cn } from '@/lib/utils'
import { uuid } from '@/lib/uuid'
import {
  PAYMENT_METHODS,
  type PaymentDirection,
  type RiderBalances,
  type RiderPayment,
  type RiderPaymentMethod,
  type RiderPaymentsPage,
  type RiderRow,
} from '../data/types'
import { sar } from './rider-parts'

const when = (iso: string) => format(toBusinessTime(iso), 'MMM d, yyyy · HH:mm')
const methodLabel = (method: string) => PAYMENT_METHODS.find((m) => m.value === method)?.label ?? method
const today = () => format(businessToday(), 'yyyy-MM-dd')

/** What each side of the sheet calls things. */
const WORDING = {
  in: {
    tab: 'Cash from rider',
    form: (name: string) => `Record a payment from ${name}`,
    amount: 'Amount received (SAR)',
    date: 'Received on',
    empty: 'Record one above when the rider hands money in.',
  },
  out: {
    tab: 'Pay to rider',
    form: (name: string) => `Record a payment to ${name}`,
    amount: 'Amount paid (SAR)',
    date: 'Paid on',
    empty: 'Record one above when KSA Drop pays the rider.',
  },
} as const

type Props = {
  rider: RiderRow
  /** Opens the rider's details, where their pay rates are set. Absent for staff who can't edit riders. */
  onEditRates?: () => void
  /** Which side opens first. */
  initialDirection?: PaymentDirection
  open: boolean
  onOpenChange: (open: boolean) => void
  /** The rider's numbers after a payment was recorded or voided. */
  onChanged: (balances: RiderBalances) => void
}

/**
 * A rider's money, both ways. Cash from rider: the COD they collected in
 * cash, what they handed in, what they still owe. Pay to rider: what they
 * earned per order, what KSA Drop paid them, what it still owes. Staff record
 * each payment here; a wrong one is voided and entered again, never edited.
 */
export function RiderPaymentsSheet({ rider, onEditRates, initialDirection = 'in', open, onOpenChange, onChanged }: Props) {
  const { can } = usePermissions()
  const canRecord = can('manage rider payments')

  const [direction, setDirection] = useState<PaymentDirection>(initialDirection)
  const [balances, setBalances] = useState<RiderBalances>({ cash: rider.cash, pay: rider.pay })
  const [payments, setPayments] = useState<RiderPayment[] | null>(null)
  const [nextPage, setNextPage] = useState<number | null>(null)
  const [loading, setLoading] = useState(false)
  const [failed, setFailed] = useState(false)
  const [voiding, setVoiding] = useState<RiderPayment | null>(null)

  const { cash, pay } = balances
  const words = WORDING[direction]
  const firstName = rider.name.split(/\s+/)[0]
  const hasRates = rider.delivery_rate !== null || rider.attempt_rate !== null

  const applyBalances = (next: RiderBalances) => {
    const picked = { cash: next.cash, pay: next.pay }
    setBalances(picked)
    onChanged(picked)
  }

  const load = async (page: number, signal?: AbortSignal) => {
    setLoading(true)
    try {
      const { data } = await axios.get<RiderPaymentsPage>(`/api/riders/${rider.id}/payments`, { params: { direction, page }, signal })
      setPayments((list) => (page === 1 ? data.payments : [...(list ?? []), ...data.payments]))
      setNextPage(data.next_page)
      applyBalances(data)
      setFailed(false)
    } catch {
      if (!signal?.aborted) setFailed(true)
    } finally {
      if (!signal?.aborted) setLoading(false)
    }
  }

  useEffect(() => {
    if (!open) return
    const controller = new AbortController()
    setPayments(null)
    load(1, controller.signal)
    return () => controller.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, rider.id, direction])

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 sm:max-w-xl'>
        <SheetHeader className='border-b'>
          <SheetTitle>{rider.name}&rsquo;s cash and pay</SheetTitle>
          <SheetDescription>
            {direction === 'in'
              ? 'COD the rider collected in cash, minus what they have handed in to KSA Drop.'
              : 'What the rider earned per order, minus what KSA Drop has paid them.'}
          </SheetDescription>

          <div className='mt-1 grid grid-cols-2 gap-1 rounded-lg bg-muted p-1'>
            {(['in', 'out'] as const).map((option) => (
              <button
                key={option}
                type='button'
                aria-pressed={direction === option}
                onClick={() => setDirection(option)}
                className={cn(
                  'h-8 rounded-md text-sm font-medium transition-colors',
                  direction === option ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                {WORDING[option].tab}
              </button>
            ))}
          </div>

          {direction === 'in' ? (
            <>
              <div className='mt-2 grid grid-cols-3 gap-2'>
                <Figure label='Collected in cash' value={sar(cash.collected)} />
                <Figure label='Handed in' value={sar(cash.paid)} tone='text-green-700 dark:text-green-400' />
                <Figure
                  label={cash.balance < 0 ? 'In credit' : 'Still owes'}
                  value={sar(Math.abs(cash.balance))}
                  tone={cash.balance > 0 ? 'text-amber-700 dark:text-amber-400' : cash.balance < 0 ? 'text-green-700 dark:text-green-400' : undefined}
                  strong
                />
              </div>
              {cash.direct > 0 && (
                <p className='text-xs text-muted-foreground'>
                  {sar(cash.direct)} more was paid by card or transfer. That reached KSA Drop directly and is not owed by the rider.
                </p>
              )}
            </>
          ) : (
            <>
              <div className='mt-2 grid grid-cols-3 gap-2'>
                <Figure label='Earned' value={sar(pay.earned)} />
                <Figure label='Paid to rider' value={sar(pay.paid)} tone='text-green-700 dark:text-green-400' />
                <Figure
                  label={pay.balance < 0 ? 'Overpaid' : 'Still to pay'}
                  value={sar(Math.abs(pay.balance))}
                  tone={pay.balance > 0 ? 'text-amber-700 dark:text-amber-400' : undefined}
                  strong
                />
              </div>
              <p className={cn('text-xs', hasRates ? 'text-muted-foreground' : 'text-amber-700 dark:text-amber-400')}>
                {hasRates
                  ? `${pay.delivered} ${pay.delivered === 1 ? 'delivery' : 'deliveries'} and ${pay.attempted} paid attempt${pay.attempted === 1 ? '' : 's'} so far. ${firstName} is paid ${sar(rider.delivery_rate ?? 0)} per delivery and ${sar(rider.attempt_rate ?? 0)} per attempt.`
                  : `No pay rates set for ${firstName} yet, so their orders earn nothing.`}{' '}
                {onEditRates && (
                  <button type='button' onClick={onEditRates} className='font-medium text-primary underline-offset-2 hover:underline'>
                    {hasRates ? 'Change rates' : 'Set rates'}
                  </button>
                )}
              </p>
            </>
          )}
        </SheetHeader>

        <div className='flex-1 overflow-y-auto'>
          {canRecord && (
            <RecordPayment
              key={direction}
              riderId={rider.id}
              riderName={firstName}
              direction={direction}
              balance={direction === 'in' ? cash.balance : pay.balance}
              onRecorded={(next) => {
                applyBalances(next)
                // A back-dated payment doesn't belong at the top: read the list again.
                load(1)
              }}
            />
          )}

          <p className='border-b px-4 pt-4 pb-2 text-sm font-medium'>Payments</p>

          {failed && payments === null ? (
            <div className='flex flex-col items-center gap-3 py-12 text-sm text-muted-foreground'>
              Could not load the payments.
              <Button variant='outline' size='sm' onClick={() => load(1)}>
                Try again
              </Button>
            </div>
          ) : payments === null ? (
            <ListSkeleton />
          ) : payments.length === 0 ? (
            <EmptyState
              bot='droid'
              title='No payments yet.'
              description={canRecord ? words.empty : 'Nothing has been recorded for this rider.'}
              className='py-10'
            />
          ) : (
            <>
              <ul className='divide-y'>
                {payments.map((payment) => (
                  <PaymentItem key={payment.id} payment={payment} onVoid={canRecord ? () => setVoiding(payment) : undefined} />
                ))}
              </ul>
              {nextPage !== null && (
                <div className='border-t p-3'>
                  <Button variant='ghost' size='sm' className='w-full' disabled={loading} onClick={() => load(nextPage)}>
                    {loading ? <Loader2 className='me-1 h-4 w-4 animate-spin' /> : null}
                    {loading ? 'Loading…' : 'Load more payments'}
                  </Button>
                </div>
              )}
            </>
          )}
        </div>

        {voiding && (
          <VoidDialog
            rider={rider}
            payment={voiding}
            onClose={() => setVoiding(null)}
            onVoided={(payment, next) => {
              setPayments((list) => list?.map((p) => (p.id === payment.id ? payment : p)) ?? null)
              applyBalances(next)
            }}
          />
        )}
      </SheetContent>
    </Sheet>
  )
}

function Figure({ label, value, tone, strong }: { label: string; value: string; tone?: string; strong?: boolean }) {
  return (
    <div className={cn('rounded-lg border p-2.5', strong && 'bg-muted/50')}>
      <p className='text-xs text-muted-foreground'>{label}</p>
      <p className={cn('mt-0.5 text-sm font-semibold tabular-nums sm:text-base', tone)}>{value}</p>
    </div>
  )
}

type FormErrors = Partial<Record<'amount' | 'method' | 'reference' | 'note' | 'received_at', string>>

function RecordPayment({
  riderId,
  riderName,
  direction,
  balance,
  onRecorded,
}: {
  riderId: number
  riderName: string
  direction: PaymentDirection
  /** What is still owed on this side. */
  balance: number
  onRecorded: (balances: RiderBalances) => void
}) {
  const words = WORDING[direction]
  const [amount, setAmount] = useState('')
  const [method, setMethod] = useState<RiderPaymentMethod>('cash')
  const [receivedOn, setReceivedOn] = useState(today)
  const [reference, setReference] = useState('')
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FormErrors>({})
  const [saving, setSaving] = useState(false)
  // One id per entry: a double click or a retry after a lost response is
  // recorded once. A new one only after the payment went through.
  const entryId = useRef(uuid())

  const value = Number(amount)
  const valid = amount.trim() !== '' && Number.isFinite(value) && value > 0
  const over = valid && value - Math.max(balance, 0) >= 0.01

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!valid || saving) return

    setSaving(true)
    setErrors({})
    try {
      const { data } = await axios.post(`/api/riders/${riderId}/payments`, {
        direction,
        amount: value.toFixed(2),
        method,
        reference: reference.trim() || null,
        note: note.trim() || null,
        // Today: the server's clock. An earlier day: midday, KSA time.
        received_at: receivedOn === today() ? null : `${receivedOn}T12:00:00+03:00`,
        client_uuid: entryId.current,
      })
      toast.success(data.message)
      entryId.current = uuid()
      setAmount('')
      setReference('')
      setNote('')
      setReceivedOn(today())
      onRecorded(data)
    } catch (err: any) {
      const fields = err.response?.data?.errors as Record<string, string[]> | undefined
      if (fields) setErrors(Object.fromEntries(Object.entries(fields).map(([key, messages]) => [key, messages[0]])))
      else toast.error(err.response?.data?.message || 'Could not record the payment.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={submit} className='space-y-3 border-b bg-muted/30 p-4'>
      <p className='text-sm font-medium'>{words.form(riderName)}</p>

      <div className='grid grid-cols-1 gap-3 sm:grid-cols-2'>
        <Field label={words.amount} htmlFor='payment-amount' error={errors.amount}>
          <div className='flex gap-2'>
            <Input
              id='payment-amount'
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              type='number'
              inputMode='decimal'
              min='0.01'
              step='0.01'
              placeholder='0.00'
              dir='ltr'
              className='tabular-nums'
            />
            {balance > 0 && (
              <Button type='button' variant='outline' className='shrink-0' onClick={() => setAmount(balance.toFixed(2))}>
                All {sar(balance)}
              </Button>
            )}
          </div>
        </Field>
        <Field label='Paid by' error={errors.method}>
          <Select value={method} onValueChange={(v) => setMethod(v as RiderPaymentMethod)}>
            <SelectTrigger className='w-full'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PAYMENT_METHODS.map((m) => (
                <SelectItem key={m.value} value={m.value}>
                  {m.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
        <Field label={words.date} htmlFor='payment-date' error={errors.received_at}>
          <Input id='payment-date' type='date' value={receivedOn} max={today()} onChange={(e) => setReceivedOn(e.target.value || today())} />
        </Field>
        <Field label='Reference (optional)' htmlFor='payment-reference' error={errors.reference}>
          <Input
            id='payment-reference'
            value={reference}
            onChange={(e) => setReference(e.target.value)}
            maxLength={100}
            placeholder='Receipt or transfer number'
          />
        </Field>
        <Field label='Note (optional)' htmlFor='payment-note' error={errors.note} className='sm:col-span-2'>
          <Input id='payment-note' value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} />
        </Field>
      </div>

      {over && (
        <p className='text-xs text-amber-700 dark:text-amber-400'>
          {direction === 'in'
            ? balance > 0
              ? `That is more than the ${sar(balance)} the rider owes. The extra is kept as credit.`
              : 'The rider owes nothing right now. This is kept as credit.'
            : balance > 0
              ? `That is more than the ${sar(balance)} owed to the rider. The extra counts against what they earn next.`
              : 'Nothing is owed to the rider right now. This counts against what they earn next.'}
        </p>
      )}

      <div className='flex justify-end'>
        <Button type='submit' disabled={!valid || saving}>
          {saving && <Loader2 className='me-1 h-4 w-4 animate-spin' />}
          {saving ? 'Recording…' : 'Record payment'}
        </Button>
      </div>
    </form>
  )
}

function Field({
  label,
  htmlFor,
  error,
  className,
  children,
}: {
  label: string
  htmlFor?: string
  error?: string
  className?: string
  children: React.ReactNode
}) {
  return (
    <div className={cn('space-y-1.5', className)}>
      <Label htmlFor={htmlFor}>{label}</Label>
      {children}
      {error && <p className='text-xs text-destructive'>{error}</p>}
    </div>
  )
}

function PaymentItem({ payment, onVoid }: { payment: RiderPayment; onVoid?: () => void }) {
  const voided = payment.voided_at !== null

  return (
    <li className='flex items-start justify-between gap-3 px-4 py-3'>
      <div className='min-w-0 text-xs text-muted-foreground'>
        <p className={cn('text-sm font-medium text-foreground', voided && 'line-through opacity-60')}>
          {methodLabel(payment.method)}
          {payment.reference && <span className='font-normal text-muted-foreground'> · {payment.reference}</span>}
        </p>
        <p className='tabular-nums'>
          {when(payment.received_at)}
          {payment.recorded_by && ` · recorded by ${payment.recorded_by}`}
        </p>
        {payment.note && <p className='mt-0.5 rounded bg-muted/60 px-2 py-1'>&ldquo;{payment.note}&rdquo;</p>}
        {voided && (
          <p className='mt-0.5 text-red-600 dark:text-red-400'>
            Voided{payment.voided_by && ` by ${payment.voided_by}`} · {when(payment.voided_at!)}
            {payment.void_reason && ` — ${payment.void_reason}`}
          </p>
        )}
      </div>
      <div className='flex shrink-0 flex-col items-end gap-1'>
        <span className={cn('text-sm font-semibold tabular-nums', voided ? 'line-through opacity-60' : 'text-green-700 dark:text-green-400')}>
          {sar(payment.amount)}
        </span>
        {!voided && onVoid && (
          <Button variant='ghost' size='sm' className='h-6 px-2 text-xs text-muted-foreground hover:text-destructive' onClick={onVoid}>
            Void
          </Button>
        )}
      </div>
    </li>
  )
}

function VoidDialog({
  rider,
  payment,
  onClose,
  onVoided,
}: {
  rider: RiderRow
  payment: RiderPayment
  onClose: () => void
  onVoided: (payment: RiderPayment, balances: RiderBalances) => void
}) {
  const [reason, setReason] = useState('')
  const [saving, setSaving] = useState(false)

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!reason.trim() || saving) return

    setSaving(true)
    try {
      const { data } = await axios.post(`/api/riders/${rider.id}/payments/${payment.id}/void`, { reason: reason.trim() })
      toast.success(data.message)
      onVoided(data.payment, data)
      onClose()
    } catch (err: any) {
      toast.error(err.response?.data?.errors?.reason?.[0] ?? err.response?.data?.message ?? 'Could not void the payment.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && !saving && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Void this payment?</DialogTitle>
          <DialogDescription>
            {payment.direction === 'out'
              ? `${sar(payment.amount)} to ${rider.name} on ${when(payment.received_at)} will no longer count as paid, so it is owed to the rider again.`
              : `${sar(payment.amount)} from ${rider.name} on ${when(payment.received_at)} will no longer count as paid, so the rider owes it again.`}{' '}
            It stays in the list, marked as voided.
          </DialogDescription>
        </DialogHeader>

        <form id='void-payment-form' onSubmit={submit} className='space-y-1.5'>
          <Label htmlFor='void-reason'>
            Why<span className='text-destructive'>*</span>
          </Label>
          <Input
            id='void-reason'
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            maxLength={255}
            placeholder='e.g. Entered for the wrong rider'
            autoFocus
          />
        </form>

        <DialogFooter>
          <Button type='button' variant='outline' onClick={onClose} disabled={saving}>
            Keep it
          </Button>
          <Button type='submit' form='void-payment-form' variant='destructive' disabled={saving || !reason.trim()}>
            {saving ? 'Voiding…' : 'Void payment'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function ListSkeleton() {
  return (
    <ul className='divide-y'>
      {Array.from({ length: 3 }).map((_, i) => (
        <li key={i} className='flex justify-between px-4 py-4'>
          <div className='space-y-2'>
            <Skeleton className='h-4 w-32' />
            <Skeleton className='h-3 w-48' />
          </div>
          <Skeleton className='h-4 w-20' />
        </li>
      ))}
    </ul>
  )
}
