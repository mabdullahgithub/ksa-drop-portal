import { useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { api, ApiError } from '../api'
import { money, reasonText, useI18n, type Lang } from '../i18n'
import { BottomSheet } from './bottom-sheet'
import type { CashPayment, CashResponse, RiderCash, RiderPay } from '../types'

/** The sheet's two accounts: the cash the rider owes, and the pay they are owed. */
export type MoneySide = 'cash' | 'pay'

type Props = {
  open: boolean
  onClose: () => void
  /** The account the rider tapped: it goes on top. */
  first: MoneySide
  /** Shown straight away while the payments load (from /rider/api/me). */
  cash: RiderCash | null
  pay: RiderPay | null
  /** The numbers as just loaded, so Home shows the same ones. */
  onLoaded: (balances: { cash: RiderCash; pay: RiderPay }) => void
}

/** How a cash balance reads: still to pay, settled, or paid more than collected. */
export const cashLabel = (balance: number) => (balance > 0 ? 'cash_to_pay' : balance < 0 ? 'cash_credit' : 'cash_settled')

/** How a pay balance reads: still due to the rider, settled, or paid ahead. */
export const payLabel = (balance: number) => (balance > 0 ? 'pay_owed' : balance < 0 ? 'pay_over' : 'pay_settled')

/** Nothing to show about pay until KSA Drop has set a rate or the rider has earned something. */
export const hasPay = (pay: RiderPay) => pay.rates.delivery > 0 || pay.rates.attempt > 0 || pay.earned > 0 || pay.paid > 0

const amount = (n: number) => n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })

// Western digits in both languages, Riyadh time (as in the Delivered list).
const when = (lang: Lang, iso: string) =>
  new Date(iso).toLocaleString(lang === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
    timeZone: 'Asia/Riyadh',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })

/**
 * The rider's money, both ways. What they owe KSA Drop: the COD cash they
 * collected, minus what they handed in. What KSA Drop owes them: their pay
 * per delivery and per attempt, minus what it has paid. Each with its
 * payments underneath; the two are never netted.
 */
export function CashSheet({ open, onClose, first, cash: initialCash, pay: initialPay, onLoaded }: Props) {
  const { t } = useI18n()
  const [data, setData] = useState<CashResponse | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = async () => {
    setError(null)
    try {
      const response = await api.get<CashResponse>('/rider/api/cash')
      setData(response)
      onLoaded({ cash: response.cash, pay: response.pay })
    } catch (e) {
      const err = e as ApiError
      setError(err.offline ? t('offline') : (reasonText(t, err.code) ?? t('something_wrong')))
    }
  }

  useEffect(() => {
    if (open) load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const cash = data?.cash ?? initialCash
  const pay = data?.pay ?? initialPay

  // What I owe KSA Drop
  const cashSection = cash && (
    <section>
      {pay && hasPay(pay) && <Heading>{t('cash_section')}</Heading>}
      <Figure label={t(cashLabel(cash.balance))} amount={cash.balance} tint={cash.balance > 0 ? undefined : '#16a34a'} />
      <dl className='mt-3 divide-y divide-foreground/[0.06] rounded-2xl bg-canvas px-3.5'>
        <Line label={t('cash_collected')} amount={cash.collected} />
        <Line label={t('cash_paid')} amount={cash.paid} tone='text-green-700 dark:text-green-400' />
      </dl>
      {cash.direct > 0 && <Note>{t('cash_direct', { amount: money(cash.direct, 'SAR') })}</Note>}

      <Heading className='mt-5'>{t('cash_payments')}</Heading>
      <Payments payments={data?.payments} empty={t('cash_no_payments')} loading={!error} />
      <Note>{t('cash_hint')}</Note>
    </section>
  )

  // What KSA Drop owes me
  const paySection = pay && hasPay(pay) && (
    <section>
      <Heading>{t('pay_section')}</Heading>
      <Figure label={t(payLabel(pay.balance))} amount={pay.balance} tint={pay.balance > 0 ? '#16a34a' : '#52525b'} />
      <dl className='mt-3 divide-y divide-foreground/[0.06] rounded-2xl bg-canvas px-3.5'>
        <Line label={t('pay_earned')} amount={pay.earned} hint={t('pay_visits', { delivered: pay.delivered, attempted: pay.attempted })} />
        <Line label={t('pay_paid')} amount={pay.paid} tone='text-green-700 dark:text-green-400' />
      </dl>
      <Note>{t('pay_rates', { delivery: amount(pay.rates.delivery), attempt: amount(pay.rates.attempt) })}</Note>

      <Heading className='mt-5'>{t('pay_payouts')}</Heading>
      <Payments payments={data?.payouts} empty={t('pay_no_payouts')} loading={!error} />
      <Note>{t('pay_rule')}</Note>
    </section>
  )

  const [top, bottom] = first === 'pay' && paySection ? [paySection, cashSection] : [cashSection, paySection]

  return (
    <BottomSheet open={open} onClose={onClose} className='max-h-[92dvh] border-0 bg-surface'>
      <div className='mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-foreground/15' />
      <SheetTitle className='px-5 pb-3 pt-4 text-[20px] font-bold'>{t('cash_title')}</SheetTitle>
      <SheetDescription className='sr-only'>{t('cash_row_hint')}</SheetDescription>

      <div className='overflow-y-auto overscroll-contain px-4 pb-[calc(env(safe-area-inset-bottom)+20px)]'>
        {error && (
          <div className='glass-tint mb-3 rounded-2xl p-3.5 text-sm' style={{ '--tint': '#dc2626' } as React.CSSProperties}>
            {error}
            <button type='button' onClick={load} className='ms-2 font-semibold underline'>
              {t('retry')}
            </button>
          </div>
        )}

        {top}
        {bottom && <div className='mt-7 border-t border-foreground/[0.08] pt-5'>{bottom}</div>}
      </div>
    </BottomSheet>
  )
}

function Heading({ className, children }: { className?: string; children: React.ReactNode }) {
  return <p className={cn('mb-1.5 px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground', className)}>{children}</p>
}

function Note({ children }: { children: React.ReactNode }) {
  return <p className='mt-2 px-1 text-[13px] leading-snug text-muted-foreground'>{children}</p>
}

/** The balance, large. Brand colour unless a tint says otherwise. */
function Figure({ label, amount, tint }: { label: string; amount: number; tint?: string }) {
  return (
    <div className='glass-tint rounded-3xl px-5 py-5 text-center' style={tint ? ({ '--tint': tint } as React.CSSProperties) : undefined}>
      <p className='text-sm font-medium text-white/90'>{label}</p>
      <p className='mt-1 text-[32px] font-bold leading-tight tabular-nums' dir='ltr'>
        {money(Math.abs(amount), 'SAR')}
      </p>
    </div>
  )
}

function Line({ label, amount, tone, hint }: { label: string; amount: number; tone?: string; hint?: string }) {
  return (
    <div className='flex items-center justify-between gap-3 py-3'>
      <dt className='min-w-0'>
        <span className='block text-[15px] text-muted-foreground'>{label}</span>
        {hint && <span className='block truncate text-xs text-muted-foreground/80'>{hint}</span>}
      </dt>
      <dd className={cn('shrink-0 text-[15px] font-semibold tabular-nums', tone)} dir='ltr'>
        {money(amount, 'SAR')}
      </dd>
    </div>
  )
}

function Payments({ payments, empty, loading }: { payments: CashPayment[] | undefined; empty: string; loading: boolean }) {
  const { t, lang } = useI18n()

  if (!payments) {
    return loading ? (
      <div className='flex justify-center py-6'>
        <Loader2 className='h-6 w-6 animate-spin text-muted-foreground' />
      </div>
    ) : null
  }

  if (payments.length === 0) {
    return <p className='rounded-2xl bg-canvas px-3.5 py-4 text-center text-sm text-muted-foreground'>{empty}</p>
  }

  return (
    <ul className='divide-y divide-foreground/[0.06] rounded-2xl bg-canvas px-3.5'>
      {payments.map((payment) => (
        <li key={payment.id} className='flex items-center justify-between gap-3 py-3'>
          <div className='min-w-0'>
            <p className='text-[15px] font-semibold'>{t(`method_${payment.method}`)}</p>
            <p className='truncate text-xs text-muted-foreground'>
              {when(lang, payment.received_at)}
              {payment.reference && <span> · {t('cash_ref', { ref: payment.reference })}</span>}
            </p>
          </div>
          <span className='shrink-0 text-[15px] font-semibold tabular-nums text-green-700 dark:text-green-400' dir='ltr'>
            {money(payment.amount, 'SAR')}
          </span>
        </li>
      ))}
    </ul>
  )
}
