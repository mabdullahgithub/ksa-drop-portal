import { MapPin, MessageCircle, Phone } from 'lucide-react'
import { cn } from '@/lib/utils'
import { money, statusText, useI18n, type TFunction } from '../i18n'
import { whatsAppNumber } from '../lib/device'
import type { Parcel } from '../types'

const STATUS_TONE: Record<string, string> = {
  info_received: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  out_for_delivery: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
  attempt_fail: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
  cancelled: 'bg-zinc-200 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
  returned: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
}

export function StatusBadge({ status, className }: { status: string; className?: string }) {
  const { t } = useI18n()
  return (
    <span
      className={cn(
        'inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold',
        STATUS_TONE[status] ?? 'bg-muted text-muted-foreground',
        className
      )}
    >
      {statusText(t, status)}
    </span>
  )
}

export function CodBox({ parcel, className }: { parcel: Parcel; className?: string }) {
  const { t } = useI18n()

  if (parcel.cod_amount <= 0) {
    return (
      <div className={cn('glass-tint rounded-2xl px-3.5 py-2.5 text-sm font-semibold', className)} style={{ '--tint': '#16a34a' } as React.CSSProperties}>
        {t('prepaid')}
      </div>
    )
  }

  return (
    <div className={cn('glass-tint flex items-baseline justify-between rounded-2xl px-3.5 py-2.5', className)}>
      <span className='text-sm font-medium text-white/90'>{t('collect')}</span>
      <span className='text-xl font-bold tabular-nums' dir='ltr'>
        {money(parcel.cod_amount, parcel.currency)}
      </span>
    </div>
  )
}

/**
 * Why the last attempt failed, with the rider's note — for a parcel still
 * waiting for another try. Null once it has moved on.
 */
export function failedWhy(t: TFunction, parcel: Parcel): string | null {
  const last = parcel.last_event
  if (parcel.status !== 'attempt_fail' || last?.action !== 'attempt_failed' || !last.reason) return null
  return [t(`reason_${last.reason}`), last.note].filter(Boolean).join(' — ')
}

export function addressLine(parcel: Parcel): string {
  const r = parcel.receiver
  return [r.address, r.area, r.city].filter(Boolean).join(', ')
}

export function ContactButtons({ parcel }: { parcel: Parcel }) {
  const { t } = useI18n()
  const phone = parcel.receiver.phone
  const address = addressLine(parcel)

  const base =
    'glass-lite glass-press flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full text-[13px] font-semibold'

  return (
    <div className='flex gap-2'>
      {phone && (
        <a href={`tel:${phone}`} className={base}>
          <Phone className='h-4 w-4 text-green-600' />
          {t('call')}
        </a>
      )}
      {phone && (
        <a href={`https://wa.me/${whatsAppNumber(phone)}`} target='_blank' rel='noopener noreferrer' className={base}>
          <MessageCircle className='h-4 w-4 text-emerald-600' />
          {t('whatsapp')}
        </a>
      )}
      {address && (
        <a
          href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`}
          target='_blank'
          rel='noopener noreferrer'
          className={base}
        >
          <MapPin className='h-4 w-4 text-sky-600' />
          {t('map')}
        </a>
      )}
    </div>
  )
}
