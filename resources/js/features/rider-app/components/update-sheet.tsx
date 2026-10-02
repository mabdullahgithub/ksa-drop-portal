import { useEffect, useMemo, useRef, useState } from 'react'
import { AlertTriangle, Ban, Camera, Check, CheckCircle2, Loader2, MessageCircle, PackageCheck, PackageX, RefreshCw, Truck } from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { api, ApiError } from '../api'
import { money, reasonText, statusText, useI18n } from '../i18n'
import { compressImage } from '@/lib/compress-image'
import { uuid, vibrate, watchPosition, type Position } from '../lib/device'
import type { EntryMethod, FailedReason, Parcel, PaymentMethod, RiderAction } from '../types'
import { addressLine, CodBox, ContactButtons, StatusBadge } from './parcel-parts'

export type UpdateRequest = {
  /** Changes on every open, so the form starts fresh each time. */
  id: number
  code: string
  entry: EntryMethod
  /** Shown straight away while the latest state loads (from the parcels list). */
  preview?: Parcel
}

type Props = {
  request: UpdateRequest | null
  onClose: () => void
  onUpdated: (parcel: Parcel) => void
  /** WhatsApp link to support about a parcel; absent until the admin sets a contact. */
  helpLink?: (trackingNumber: string) => string
}

/**
 * Opens after every scan. Shows the parcel, pre-selects the next status,
 * and asks only for what that status needs. Update sends it; Cancel closes
 * without changing anything.
 */
export function UpdateSheet({ request, onClose, onUpdated, helpLink }: Props) {
  const { t } = useI18n()

  return (
    <Sheet open={request !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent
        side='bottom'
        className='glass-strong max-h-[94dvh] gap-0 rounded-t-[32px] p-0'
        // Don't focus the first control on open: it would ring the close
        // button, or pop the keyboard up over the parcel details.
        onOpenAutoFocus={(event) => event.preventDefault()}
      >
        <SheetTitle className='sr-only'>{t('what_happened')}</SheetTitle>
        <SheetDescription className='sr-only'>{request?.code}</SheetDescription>
        {request && <UpdateBody key={request.id} request={request} onClose={onClose} onUpdated={onUpdated} helpLink={helpLink} />}
      </SheetContent>
    </Sheet>
  )
}

/** Each action's colour — orange out, green delivered, red failed. */
const ACTION_STYLE: Record<RiderAction, { icon: typeof Truck; tint: string }> = {
  out_for_delivery: { icon: Truck, tint: 'var(--brand)' },
  delivered: { icon: PackageCheck, tint: '#16a34a' },
  attempt_failed: { icon: PackageX, tint: '#dc2626' },
}

const tint = (color: string) => ({ '--tint': color }) as React.CSSProperties

const REASONS: FailedReason[] = ['no_answer', 'refused', 'wrong_address', 'reschedule', 'no_cash', 'closed', 'other']
const PAYMENT_METHODS: PaymentMethod[] = ['cash', 'card', 'transfer']

/** Arabic keyboards type Arabic-Indic digits and the Arabic decimal mark. */
function toNumber(value: string): number {
  const western = value
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/[٫,]/g, '.')
    .trim()
  return western === '' ? NaN : Number(western)
}

function UpdateBody({ request, onClose, onUpdated, helpLink }: { request: UpdateRequest } & Omit<Props, 'request'>) {
  const { t } = useI18n()

  const [parcel, setParcel] = useState<Parcel | null>(request.preview ?? null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [action, setAction] = useState<RiderAction | null>(request.preview?.allowed_actions[0] ?? null)
  const [cod, setCod] = useState('')
  const [payment, setPayment] = useState<PaymentMethod>('cash')
  const [recipient, setRecipient] = useState('')
  const [reason, setReason] = useState<FailedReason | null>(null)
  const [note, setNote] = useState('')
  const [photo, setPhoto] = useState<{ blob: Blob; url: string } | null>(null)
  const [preparingPhoto, setPreparingPhoto] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  const positionRef = useRef<Position | null>(null)
  const photoInputRef = useRef<HTMLInputElement>(null)
  // One id per action: resending the same update after a lost response is
  // recorded once, but switching to a different action is a new update.
  const uuidsRef = useRef<Partial<Record<RiderAction, string>>>({})

  const load = async () => {
    setLoading(true)
    setLoadError(null)
    try {
      const { parcel } = await api.get<{ parcel: Parcel }>(`/rider/api/scan?code=${encodeURIComponent(request.code)}`)
      setParcel(parcel)
      setAction((current) => (current && parcel.allowed_actions.includes(current) ? current : parcel.allowed_actions[0] ?? null))
      setCod((current) => current || (parcel.cod_amount > 0 ? parcel.cod_amount.toFixed(2) : ''))
    } catch (error) {
      const e = error as ApiError
      if (e.status === 404) {
        setLoadError(t('not_found', { code: request.code }))
        vibrate([60, 80, 60])
      } else {
        setLoadError(reasonText(t, e.code) ?? t('something_wrong'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    return watchPosition((position) => {
      positionRef.current = position
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => () => {
    if (photo) URL.revokeObjectURL(photo.url)
  }, [photo])

  const expected = parcel?.cod_amount ?? 0
  const codValue = toNumber(cod)
  const amountDiffers = action === 'delivered' && expected > 0 && !Number.isNaN(codValue) && Math.abs(codValue - expected) >= 0.01

  const problem = useMemo(() => {
    if (!parcel || !action) return null
    if (action === 'delivered') {
      if (expected > 0 && (Number.isNaN(codValue) || codValue < 0)) return t('need_amount')
      if (amountDiffers && !note.trim()) return t('amount_differs', { amount: money(expected, parcel.currency) })
    }
    if (action === 'attempt_failed') {
      if (!reason) return t('need_reason')
      if (reason === 'other' && !note.trim()) return t('need_note')
    }
    return null
  }, [parcel, action, expected, codValue, amountDiffers, note, reason, t])

  const takePhoto = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    setPreparingPhoto(true)
    try {
      const blob = await compressImage(file)
      setPhoto({ blob, url: URL.createObjectURL(blob) })
    } catch {
      setPhoto({ blob: file, url: URL.createObjectURL(file) })
    } finally {
      setPreparingPhoto(false)
    }
  }

  const submit = async () => {
    if (!parcel || !action || problem || submitting) return

    const clientUuid = (uuidsRef.current[action] ??= uuid())
    const form = new FormData()
    form.append('action', action)
    form.append('client_uuid', clientUuid)
    form.append('entry_method', request.entry)
    form.append('occurred_at', new Date().toISOString())
    if (note.trim()) form.append('note', note.trim())

    if (action === 'delivered') {
      if (expected > 0) {
        form.append('cod_amount', codValue.toFixed(2))
        form.append('payment_method', payment)
      }
      if (recipient.trim()) form.append('recipient_name', recipient.trim())
      if (photo) form.append('photo', photo.blob, 'delivery.jpg')
    }
    if (action === 'attempt_failed' && reason) form.append('reason', reason)

    const position = positionRef.current
    if (position) {
      form.append('lat', String(position.lat))
      form.append('lng', String(position.lng))
      form.append('accuracy_m', String(position.accuracy))
    }

    setSubmitting(true)
    try {
      const result = await api.post<{ parcel: Parcel }>(`/rider/api/shipments/${parcel.id}/events`, form)
      vibrate(120)
      toast.success(t('updated', { action: t(`action_${action}`) }))
      onUpdated(result.parcel)
      onClose()
    } catch (error) {
      const e = error as ApiError
      if (e.status === 409 && e.data?.parcel) {
        setParcel(e.data.parcel)
        setAction(e.data.parcel.allowed_actions[0] ?? null)
      }
      toast.error(e.offline ? t('offline') : (reasonText(t, e.code) ?? e.firstMessage))
    } finally {
      setSubmitting(false)
    }
  }

  // ── Loading / not found ────────────────────────────────────────────────
  if (!parcel) {
    return (
      <div className='flex flex-col items-center gap-4 px-6 pb-[calc(env(safe-area-inset-bottom)+24px)] pt-10 text-center'>
        {loading ? (
          <>
            <Loader2 className='h-10 w-10 animate-spin text-brand' />
            <p className='font-mono text-lg'>{request.code}</p>
          </>
        ) : (
          <>
            <AlertTriangle className='h-12 w-12 text-red-600' />
            <p className='text-base font-semibold'>{loadError}</p>
            <div className='flex w-full gap-2'>
              <button type='button' onClick={onClose} className='glass-lite glass-press h-12 flex-1 rounded-full text-[15px] font-semibold'>
                {t('close')}
              </button>
              <button type='button' onClick={load} className='glass-tint glass-press h-12 flex-1 rounded-full text-[15px] font-semibold'>
                {t('retry')}
              </button>
            </div>
          </>
        )}
      </div>
    )
  }

  // Nothing left to do with a delivered parcel: no call, map or cash — just say so.
  if (parcel.status === 'delivered') {
    return <AlreadyDelivered parcel={parcel} onClose={onClose} />
  }

  const canUpdate = parcel.allowed_actions.length > 0

  return (
    <>
      {/* Grab handle; also keeps the sheet's close button clear of the
          scrolling content below. */}
      <div className='flex h-11 shrink-0 items-start justify-center pt-2.5'>
        <span className='h-1.5 w-12 rounded-full bg-muted-foreground/30' />
      </div>
      <div className='overflow-y-auto overscroll-contain px-4 pb-4'>
        {/* Header */}
        <div className='flex items-start justify-between gap-3'>
          <div className='min-w-0'>
            <p className='font-mono text-lg font-bold tracking-tight' dir='ltr'>{parcel.tracking_number}</p>
            {parcel.order_number && <p className='text-sm text-muted-foreground' dir='ltr'>#{parcel.order_number.replace(/^#/, '')}</p>}
          </div>
          <div className='flex items-center gap-2'>
            {loading && <RefreshCw className='h-4 w-4 animate-spin text-muted-foreground' />}
            <StatusBadge status={parcel.status} />
          </div>
        </div>

        {/* Receiver */}
        <div className='mt-4 space-y-3'>
          <div>
            <p className='text-base font-semibold leading-tight'>{parcel.receiver.name}</p>
            {parcel.receiver.phone && <p className='text-sm text-muted-foreground' dir='ltr'>{parcel.receiver.phone}</p>}
            <p className='mt-1 text-sm'>{addressLine(parcel)}</p>
          </div>
          <ContactButtons parcel={parcel} />
          <CodBox parcel={parcel} />

          <div className='flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground'>
            <span>{t('pieces', { n: parcel.pieces })}</span>
            {parcel.attempts > 0 && <span className='font-semibold text-red-600'>{t('attempts', { n: parcel.attempts })}</span>}
          </div>
          {parcel.items.length > 0 && (
            <p className='line-clamp-2 text-sm text-muted-foreground'>
              {parcel.items.map((i) => `${i.name} ×${i.quantity}`).join(', ')}
            </p>
          )}
          {parcel.remark && (
            <p className='glass-lite rounded-2xl px-3.5 py-2.5 text-sm'>
              <span className='font-semibold'>{t('note_from_office')}: </span>
              {parcel.remark}
            </p>
          )}
          {helpLink && (
            <a
              href={helpLink(parcel.tracking_number)}
              target='_blank'
              rel='noopener noreferrer'
              className='flex items-center gap-1.5 text-[13px] font-semibold text-emerald-700 dark:text-emerald-400'
            >
              <MessageCircle className='h-4 w-4' />
              {t('parcel_help')}
            </a>
          )}
        </div>

        {/* Can't be updated */}
        {!canUpdate && <BlockedBanner parcel={parcel} />}

        {/* Choose what happened */}
        {canUpdate && (
          <div className='mt-5 space-y-2'>
            <p className='text-sm font-semibold text-muted-foreground'>{t('what_happened')}</p>
            {parcel.allowed_actions.map((option) => {
              const style = ACTION_STYLE[option]
              const Icon = style.icon
              const selected = action === option
              return (
                <button
                  key={option}
                  type='button'
                  onClick={() => setAction(option)}
                  aria-pressed={selected}
                  className={cn(
                    'glass-press flex w-full items-center gap-3 rounded-2xl px-3.5 py-3 text-start',
                    selected ? 'glass-tint' : 'glass-lite'
                  )}
                  style={selected ? tint(style.tint) : undefined}
                >
                  <Icon className='h-6 w-6 shrink-0' />
                  <span className='min-w-0 flex-1'>
                    <span className='block text-[15px] font-semibold'>{t(`action_${option}`)}</span>
                    <span className={cn('block text-xs', selected ? 'text-white/85' : 'text-muted-foreground')}>{t(`hint_${option}`)}</span>
                  </span>
                  <span
                    className={cn(
                      'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2',
                      selected ? 'border-current' : 'border-muted-foreground/40'
                    )}
                  >
                    {selected && <span className='h-3 w-3 rounded-full bg-current' />}
                  </span>
                </button>
              )
            })}
          </div>
        )}

        {/* Delivered: cash, photo, who received it */}
        {canUpdate && action === 'delivered' && (
          <div className='mt-5 space-y-4'>
            {expected > 0 && (
              <>
                <label className='block space-y-1.5'>
                  <span className='text-sm font-semibold'>{t('amount_collected')}</span>
                  <input
                    value={cod}
                    onChange={(e) => setCod(e.target.value)}
                    inputMode='decimal'
                    dir='ltr'
                    className={cn(
                      'glass-lite h-12 w-full rounded-2xl px-3.5 text-xl font-bold tabular-nums outline-none focus:outline-2 focus:outline-brand',
                      amountDiffers && 'outline-2 outline-amber-500'
                    )}
                  />
                  {amountDiffers && (
                    <span className='block text-sm text-amber-700 dark:text-amber-400'>
                      {t('amount_differs', { amount: money(expected, parcel.currency) })}
                    </span>
                  )}
                </label>
                <div className='space-y-1.5'>
                  <span className='text-sm font-semibold'>{t('paid_by')}</span>
                  <div className='grid grid-cols-3 gap-2'>
                    {PAYMENT_METHODS.map((method) => (
                      <button
                        key={method}
                        type='button'
                        onClick={() => setPayment(method)}
                        aria-pressed={payment === method}
                        className={cn('glass-press h-10 rounded-full text-sm font-semibold', payment === method ? 'glass-tint' : 'glass-lite')}
                        style={payment === method ? tint('#16a34a') : undefined}
                      >
                        {t(`pay_${method}`)}
                      </button>
                    ))}
                  </div>
                </div>
              </>
            )}

            <div className='space-y-1.5'>
              <span className='text-sm font-semibold'>{t('photo_optional')}</span>
              <input ref={photoInputRef} type='file' accept='image/*' capture='environment' className='hidden' onChange={takePhoto} />
              {photo ? (
                <div className='flex items-center gap-3'>
                  <img src={photo.url} alt='' className='h-16 w-16 rounded-2xl object-cover shadow-md' />
                  <button
                    type='button'
                    onClick={() => photoInputRef.current?.click()}
                    className='glass-lite glass-press flex h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold'
                  >
                    <Camera className='h-5 w-5' />
                    {t('retake')}
                  </button>
                  <CheckCircle2 className='ms-auto h-6 w-6 text-green-600' />
                </div>
              ) : (
                <button
                  type='button'
                  onClick={() => photoInputRef.current?.click()}
                  disabled={preparingPhoto}
                  className='glass-lite glass-press flex h-12 w-full items-center justify-center gap-2 rounded-2xl text-[15px] font-semibold'
                >
                  {preparingPhoto ? <Loader2 className='h-5 w-5 animate-spin' /> : <Camera className='h-5 w-5' />}
                  {t('take_photo')}
                </button>
              )}
            </div>

            <label className='block space-y-1.5'>
              <span className='text-sm font-semibold'>{t('received_by')}</span>
              <input
                value={recipient}
                onChange={(e) => setRecipient(e.target.value)}
                className='glass-lite h-11 w-full rounded-2xl px-3.5 outline-none focus:outline-2 focus:outline-brand'
              />
            </label>
          </div>
        )}

        {/* Failed: why */}
        {canUpdate && action === 'attempt_failed' && (
          <div className='mt-5 space-y-1.5'>
            <span className='text-sm font-semibold'>{t('why_failed')}</span>
            <div className='grid grid-cols-2 gap-2'>
              {REASONS.map((option) => (
                <button
                  key={option}
                  type='button'
                  onClick={() => setReason(option)}
                  aria-pressed={reason === option}
                  className={cn(
                    'glass-press min-h-10 rounded-2xl px-3.5 py-2 text-start text-[13px] font-semibold',
                    reason === option ? 'glass-tint' : 'glass-lite',
                    option === 'other' && 'col-span-2'
                  )}
                  style={reason === option ? tint('#dc2626') : undefined}
                >
                  {t(`reason_${option}`)}
                </button>
              ))}
            </div>
          </div>
        )}

        {canUpdate && action && (
          <label className='mt-4 block space-y-1.5'>
            <span className='text-sm font-semibold'>
              {(action === 'attempt_failed' && reason === 'other') || amountDiffers ? t('note') : t('note_optional')}
            </span>
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={2}
              maxLength={500}
              className='glass-lite w-full resize-none rounded-2xl px-3.5 py-2.5 outline-none focus:outline-2 focus:outline-brand'
            />
          </label>
        )}
      </div>

      {/* Update / Cancel */}
      <div className='border-t border-foreground/10 px-4 pb-[calc(env(safe-area-inset-bottom)+12px)] pt-3'>
        {canUpdate && problem && <p className='mb-2 text-center text-sm text-muted-foreground'>{problem}</p>}
        <div className='flex gap-3'>
          <button
            type='button'
            onClick={onClose}
            disabled={submitting}
            className='glass-lite glass-press h-12 flex-1 rounded-full text-[15px] font-semibold'
          >
            {canUpdate ? t('cancel') : t('close')}
          </button>
          {canUpdate && action && (
            <button
              type='button'
              onClick={submit}
              disabled={!!problem || submitting}
              className='glass-tint glass-press flex h-12 flex-[2] items-center justify-center gap-2 rounded-full text-[15px] font-bold disabled:opacity-40'
              style={tint(ACTION_STYLE[action].tint)}
            >
              {submitting && <Loader2 className='h-5 w-5 animate-spin' />}
              {submitting ? t('updating') : t(`action_${action}`)}
            </button>
          )}
        </div>
      </div>
    </>
  )
}

function AlreadyDelivered({ parcel, onClose }: { parcel: Parcel; onClose: () => void }) {
  const { t, lang } = useI18n()
  const at = parcel.last_event?.action === 'delivered' ? parcel.last_event.occurred_at : null

  // Western digits in both languages, Riyadh time (as in the Delivered list).
  const when = (iso: string) =>
    new Date(iso).toLocaleString(lang === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
      timeZone: 'Asia/Riyadh',
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })

  return (
    <>
      <div className='flex h-11 shrink-0 items-start justify-center pt-2.5'>
        <span className='h-1.5 w-12 rounded-full bg-muted-foreground/30' />
      </div>
      <div className='flex flex-col items-center px-6 pb-8 pt-2 text-center'>
        <span className='flex h-28 w-28 items-center justify-center rounded-full bg-[#16a34a] text-white duration-300 animate-in fade-in zoom-in-50 motion-reduce:animate-none'>
          <Check className='h-16 w-16' strokeWidth={3} />
        </span>
        <p className='mt-5 text-2xl font-bold'>{t('already_delivered')}</p>
        {at && <p className='mt-1 text-sm text-muted-foreground'>{t('delivered_on', { time: when(at) })}</p>}
        <p className='mt-5 font-mono text-lg font-bold' dir='ltr'>
          {parcel.tracking_number}
        </p>
        {parcel.receiver.name && <p className='mt-1 text-[15px] font-semibold'>{parcel.receiver.name}</p>}
        <p className='mt-0.5 text-sm text-muted-foreground'>{addressLine(parcel)}</p>
      </div>
      <div className='border-t border-foreground/10 px-4 pb-[calc(env(safe-area-inset-bottom)+12px)] pt-3'>
        <button type='button' onClick={onClose} className='glass-lite glass-press h-12 w-full rounded-full text-[15px] font-semibold'>
          {t('close')}
        </button>
      </div>
    </>
  )
}

function BlockedBanner({ parcel }: { parcel: Parcel }) {
  const { t } = useI18n()

  const text =
    parcel.blocked === 'finished'
      ? t('blocked_stop', { status: statusText(t, parcel.status).toUpperCase() })
      : parcel.blocked === 'held_by_other'
        ? t('blocked_held_by_other', { name: parcel.held_by ?? '—' })
        : t('blocked_not_ksa_express')

  return (
    <div
      className='glass-tint mt-4 flex items-center gap-3 rounded-2xl p-3.5'
      style={tint(parcel.blocked === 'finished' ? '#dc2626' : '#d97706')}
    >
      <Ban className='h-6 w-6 shrink-0' />
      <p className='text-sm font-semibold'>{text}</p>
    </div>
  )
}
