import { useState } from 'react'
import { KeyRound, Languages, Loader2 } from 'lucide-react'
import { api, ApiError } from '../api'
import { reasonText, useI18n } from '../i18n'
import { isStandalone } from '../lib/device'
import { assetUrl, host, type SignInAnswer } from '../lib/host'

/**
 * Phone + PIN — the fallback when the activation link can't be used. The
 * admin generates the PIN on the Riders page and gives it to the rider.
 */
export function SignInScreen({ reason }: { reason: string | null }) {
  const { t, toggle } = useI18n()
  const [phone, setPhone] = useState('')
  const [pin, setPin] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const notice = reasonText(t, reason)

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setError(null)
    try {
      const answer = await api.post<SignInAnswer>('/rider/api/login', {
        phone,
        pin: pin.replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))),
        standalone: isStandalone(),
      })
      await host().signedIn(answer)
    } catch (e) {
      const err = e as ApiError
      setError(reasonText(t, err.code) ?? err.firstMessage)
      setSubmitting(false)
    }
  }

  return (
    <div className='flex min-h-dvh flex-col px-6 pb-[calc(env(safe-area-inset-bottom)+24px)] pt-[calc(env(safe-area-inset-top)+16px)]'>
      <div className='flex justify-end'>
        <button type='button' onClick={toggle} className='glass glass-press flex h-10 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold'>
          <Languages className='h-4 w-4' />
          {t('language')}
        </button>
      </div>

      <div className='mt-6 flex flex-col items-center text-center'>
        <img src={assetUrl('/rider-icons/icon-192.png')} alt='' className='h-16 w-16 rounded-[20px] shadow-lg' />
        <h1 className='mt-3 text-xl font-bold'>{t('app_name')}</h1>
        <p className='mt-1.5 text-sm text-muted-foreground'>{t('sign_in_intro')}</p>
      </div>

      {notice && (
        <div className='glass-tint mt-6 rounded-2xl p-3.5 text-sm font-medium' style={{ '--tint': '#d97706' } as React.CSSProperties}>
          {notice}
        </div>
      )}

      <form onSubmit={submit} className='glass mt-6 space-y-4 rounded-[28px] p-5'>
        <label className='block space-y-1.5'>
          <span className='text-sm font-semibold'>{t('phone')}</span>
          <input
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            type='tel'
            inputMode='tel'
            autoComplete='tel'
            placeholder='05XXXXXXXX / 03XXXXXXXXX'
            dir='ltr'
            required
            className='glass-lite h-12 w-full rounded-2xl px-3.5 outline-none focus:outline-2 focus:outline-brand'
          />
        </label>
        <label className='block space-y-1.5'>
          <span className='text-sm font-semibold'>{t('pin')}</span>
          <input
            value={pin}
            onChange={(e) => setPin(e.target.value.slice(0, 6))}
            type='password'
            inputMode='numeric'
            autoComplete='one-time-code'
            placeholder='••••••'
            dir='ltr'
            required
            className='glass-lite h-12 w-full rounded-2xl px-3.5 text-center text-xl tracking-[0.5em] outline-none focus:outline-2 focus:outline-brand'
          />
        </label>

        {error && <p className='rounded-xl bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200'>{error}</p>}

        <button
          type='submit'
          disabled={submitting || !phone.trim() || pin.length < 6}
          className='glass-tint glass-press flex h-12 w-full items-center justify-center gap-2 rounded-full text-[15px] font-bold disabled:opacity-50'
        >
          {submitting ? <Loader2 className='h-5 w-5 animate-spin' /> : <KeyRound className='h-5 w-5' />}
          {submitting ? t('signing_in') : t('sign_in')}
        </button>
      </form>

      <p className='mt-auto pt-8 text-center text-sm text-muted-foreground'>{t('no_pin_help')}</p>
    </div>
  )
}
