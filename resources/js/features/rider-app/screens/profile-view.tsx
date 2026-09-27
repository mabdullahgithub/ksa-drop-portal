import { useRef, useState } from 'react'
import { BookOpen, Camera, ChevronRight, Languages, LifeBuoy, Loader2, LogOut, MessageCircle, type LucideIcon } from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { compressImage } from '@/lib/compress-image'
import { api, ApiError } from '../api'
import { RiderPhoto } from '../components/rider-photo'
import { reasonText, useI18n, type Lang } from '../i18n'
import { useBackToClose } from '../lib/back-button'
import type { Me } from '../types'
import { Onboarding } from './onboarding'

type Props = {
  me: Me | null
  onPhotoChanged: (photoUrl: string) => void
}

/** wa.me link that opens a chat with the message already typed. */
export function supportLink(digits: string, text: string) {
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
}

const LANGUAGES: { value: Lang; label: string }[] = [
  { value: 'en', label: 'English' },
  { value: 'ar', label: 'العربية' },
]

/** Who's signed in, their photo, the app language, the guide and support. */
export function ProfileView({ me, onPhotoChanged }: Props) {
  const { t, lang, setLang } = useI18n()
  const input = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [guide, setGuide] = useState(false)
  const [confirmingLogout, setConfirmingLogout] = useState(false)

  useBackToClose(guide, () => setGuide(false))
  useBackToClose(confirmingLogout, () => setConfirmingLogout(false))

  const pickPhoto = () => input.current?.click()

  const choose = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    setUploading(true)
    try {
      const blob = await compressImage(file, 640, 0.8)
      const form = new FormData()
      form.append('photo', blob, 'me.jpg')
      const { photo_url } = await api.post<{ photo_url: string }>('/rider/api/me/photo', form)
      onPhotoChanged(photo_url)
      toast.success(t('photo_updated'))
    } catch (error) {
      const e = error as ApiError
      toast.error(e.offline ? t('offline') : (reasonText(t, e.code) ?? e.firstMessage))
    } finally {
      setUploading(false)
    }
  }

  if (!me) {
    return (
      <div className='flex justify-center py-16'>
        <Loader2 className='h-7 w-7 animate-spin text-muted-foreground' />
      </div>
    )
  }

  return (
    <div className='space-y-4 px-4 pb-36 pt-[calc(env(safe-area-inset-top)+16px)]'>
      <h1 className='px-1 pb-1 text-[32px] font-extrabold leading-tight tracking-tight rtl:tracking-normal'>{t('nav_profile')}</h1>

      <section className='rounded-[28px] bg-surface px-5 pb-5 pt-7 text-center'>
        <button type='button' onClick={pickPhoto} disabled={uploading} className='relative mx-auto block' aria-label={t('change_photo')}>
          <RiderPhoto name={me.rider.name} photoUrl={me.rider.photo_url} className='h-28 w-28 bg-brand text-3xl text-white' />
          <span className='absolute bottom-0.5 end-0.5 flex h-9 w-9 items-center justify-center rounded-full bg-foreground text-canvas ring-4 ring-surface'>
            {uploading ? <Loader2 className='h-4 w-4 animate-spin' /> : <Camera className='h-4 w-4' />}
          </span>
        </button>
        {/* No `capture`: the phone offers camera or gallery. */}
        <input ref={input} type='file' accept='image/*' className='hidden' onChange={choose} />

        <p className='mt-4 text-[24px] font-bold leading-tight'>{me.rider.name}</p>
        <p className='mt-2 inline-flex items-center gap-1.5 rounded-full bg-brand/10 px-3 py-1 text-[13px] font-semibold text-orange-700 dark:text-orange-300'>
          <span className='h-1.5 w-1.5 rounded-full bg-brand' />
          {t('rider_role')}
        </p>

        <dl className={cn('mt-6 grid border-t border-foreground/[0.08] pt-4', me.rider.hub ? 'grid-cols-2' : 'grid-cols-1')}>
          <Info label={t('phone_label')}>
            <span dir='ltr'>{me.rider.phone}</span>
          </Info>
          {me.rider.hub && (
            <Info label={t('hub_label')} className='border-s border-foreground/[0.08]'>
              {me.rider.hub}
            </Info>
          )}
        </dl>
      </section>

      <section className='overflow-hidden rounded-[24px] bg-surface'>
        <Row
          icon={Camera}
          color='var(--brand)'
          label={me.rider.photo_url ? t('change_photo') : t('add_photo')}
          hint={t('photo_hint')}
          busy={uploading}
          onClick={pickPhoto}
        />
        <Row icon={BookOpen} color='#2563eb' label={t('app_guide')} hint={t('app_guide_hint')} onClick={() => setGuide(true)} />
      </section>

      <section className='rounded-[24px] bg-surface p-4'>
        <div className='flex items-center gap-3.5'>
          <IconTile icon={Languages} color='#7c3aed' />
          <p className='text-[16px] font-semibold'>{t('language_label')}</p>
        </div>
        <div className='mt-3.5 grid grid-cols-2 gap-1 rounded-2xl bg-canvas p-1'>
          {LANGUAGES.map((option) => (
            <button
              key={option.value}
              type='button'
              onClick={() => setLang(option.value)}
              aria-pressed={lang === option.value}
              className={cn(
                'h-12 rounded-xl text-[16px] font-semibold transition-colors',
                lang === option.value ? 'bg-foreground text-canvas' : 'text-muted-foreground'
              )}
            >
              {option.label}
            </button>
          ))}
        </div>
      </section>

      {me.support ? (
        <section className='rounded-[24px] bg-surface p-4'>
          <div className='flex items-center gap-3.5'>
            <IconTile icon={LifeBuoy} color='#15803d' />
            <div className='min-w-0'>
              <p className='text-[16px] font-semibold'>{t('support_title')}</p>
              <p className='mt-0.5 text-[13px] leading-snug text-muted-foreground'>{t('support_intro')}</p>
            </div>
          </div>
          <a
            href={supportLink(me.support.whatsapp_digits, t('support_message', { name: me.rider.name, phone: me.rider.phone }))}
            target='_blank'
            rel='noopener noreferrer'
            className='mt-4 flex h-14 w-full items-center justify-center gap-2 rounded-full bg-[#15803d] text-[16px] font-semibold text-white transition-transform active:scale-[0.98]'
          >
            <MessageCircle className='h-5 w-5' />
            {t('contact_support')}
          </a>
          <p className='mt-2.5 text-center text-[13px] text-muted-foreground'>
            {me.support.name && <span>{me.support.name} · </span>}
            <span dir='ltr'>{me.support.whatsapp}</span>
          </p>
        </section>
      ) : (
        <section className='flex items-center gap-3.5 rounded-[24px] bg-surface p-4'>
          <IconTile icon={LifeBuoy} color='#64748b' />
          <p className='text-[14px] leading-snug text-muted-foreground'>{t('help_text')}</p>
        </section>
      )}

      <button
        type='button'
        onClick={() => setConfirmingLogout(true)}
        className='flex h-14 w-full items-center justify-center gap-2 rounded-[24px] bg-surface text-[16px] font-semibold text-red-600 transition-colors active:bg-red-50 dark:text-red-400 dark:active:bg-red-950/40'
      >
        <LogOut className='h-5 w-5 rtl:rotate-180' />
        {t('log_out')}
      </button>

      <p className='pt-2 text-center text-xs text-muted-foreground'>{t('app_name')}</p>

      {guide && <Onboarding startAt='guide' onDone={() => setGuide(false)} />}
      <LogoutSheet open={confirmingLogout} onClose={() => setConfirmingLogout(false)} />
    </div>
  )
}

/** Are you sure? Getting back in takes a PIN or a new link. */
function LogoutSheet({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { t } = useI18n()
  const [busy, setBusy] = useState(false)

  const logOut = async () => {
    setBusy(true)
    try {
      const { redirect } = await api.post<{ redirect: string }>('/rider/api/logout')
      window.location.replace(redirect)
    } catch (error) {
      const e = error as ApiError
      toast.error(e.offline ? t('offline') : (reasonText(t, e.code) ?? t('something_wrong')))
      setBusy(false)
    }
  }

  return (
    <Sheet open={open} onOpenChange={(next) => !next && !busy && onClose()}>
      <SheetContent
        data-no-pull
        side='bottom'
        className='gap-0 rounded-t-[32px] border-0 bg-surface p-0 px-5 pb-[calc(env(safe-area-inset-bottom)+16px)]'
        onOpenAutoFocus={(event) => event.preventDefault()}
      >
        <div className='mx-auto mt-2.5 h-1.5 w-10 rounded-full bg-foreground/15' />
        <span className='mx-auto mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-red-600 text-white'>
          <LogOut className='h-6 w-6 rtl:rotate-180' />
        </span>
        <SheetTitle className='mt-4 text-center text-[20px] font-bold'>{t('log_out_title')}</SheetTitle>
        <SheetDescription className='mx-auto mt-2 max-w-[20rem] text-center text-[15px] leading-snug text-muted-foreground'>
          {t('log_out_text')}
        </SheetDescription>

        <button
          type='button'
          onClick={logOut}
          disabled={busy}
          className='mt-6 flex h-14 w-full items-center justify-center gap-2 rounded-full bg-red-600 text-[16px] font-semibold text-white transition-transform active:scale-[0.98] disabled:opacity-70'
        >
          {busy && <Loader2 className='h-5 w-5 animate-spin' />}
          {busy ? t('logging_out') : t('log_out')}
        </button>
        <button
          type='button'
          onClick={onClose}
          disabled={busy}
          className='mt-2 h-14 w-full rounded-full text-[16px] font-semibold text-muted-foreground active:bg-foreground/5'
        >
          {t('cancel')}
        </button>
      </SheetContent>
    </Sheet>
  )
}

function Info({ label, className, children }: { label: string; className?: string; children: React.ReactNode }) {
  return (
    <div className={cn('min-w-0 px-2', className)}>
      <dt className='text-[12px] font-medium text-muted-foreground'>{label}</dt>
      <dd className='mt-0.5 truncate text-[16px] font-semibold'>{children}</dd>
    </div>
  )
}

function IconTile({ icon: Icon, color }: { icon: LucideIcon; color: string }) {
  return (
    <span className='flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] text-white' style={{ background: color }}>
      <Icon className='h-[18px] w-[18px]' />
    </span>
  )
}

function Row({
  icon,
  color,
  label,
  hint,
  busy,
  onClick,
}: {
  icon: LucideIcon
  color: string
  label: string
  hint?: string
  busy?: boolean
  onClick: () => void
}) {
  return (
    <button
      type='button'
      onClick={onClick}
      disabled={busy}
      className='flex min-h-[68px] w-full items-center gap-3.5 border-t border-foreground/[0.06] px-4 py-3 text-start transition-colors first:border-t-0 active:bg-foreground/[0.04] disabled:opacity-60'
    >
      <IconTile icon={icon} color={color} />
      <span className='min-w-0 flex-1'>
        <span className='block text-[16px] font-semibold leading-snug'>{label}</span>
        {hint && <span className='mt-0.5 block text-[13px] leading-snug text-muted-foreground'>{hint}</span>}
      </span>
      {busy ? (
        <Loader2 className='h-5 w-5 shrink-0 animate-spin text-muted-foreground' />
      ) : (
        <ChevronRight className='h-5 w-5 shrink-0 text-muted-foreground rtl:rotate-180' />
      )}
    </button>
  )
}
