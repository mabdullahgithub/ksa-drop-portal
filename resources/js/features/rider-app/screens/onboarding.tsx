import { useEffect, useRef, useState } from 'react'
import { ArrowLeft, ArrowRight, Box, House, Languages, Lightbulb, PackageCheck, PackageX, ScanLine, Wallet, type LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useI18n, type TFunction } from '../i18n'
import { isWebKit } from '../lib/device'
import { cssColor, useStatusBarColor } from '../lib/status-bar'

type Key = Parameters<TFunction>[0]

const SEEN_KEY = 'rider_onboarded'
const MEDIA = '/rider-icons/rider-welcome-animated-icon'

/** Whether this phone has seen the welcome. Storage blocked: don't show it on every open. */
export function onboardingSeen(): boolean {
  try {
    return localStorage.getItem(SEEN_KEY) === '1'
  } catch {
    return true
  }
}

function markSeen() {
  try {
    localStorage.setItem(SEEN_KEY, '1')
  } catch {
    // Not remembered; it shows again next open.
  }
}

/**
 * Show the welcome again after each install. On Android, uninstalling the app
 * leaves Chrome's storage for the site in place, so without this a reinstall
 * would remember the welcome from last time. Chrome fires appinstalled in the
 * tab the app was installed from; iPhone doesn't, but a home screen app there
 * doesn't share storage with Safari anyway.
 */
export function resetOnboardingOnInstall() {
  window.addEventListener('appinstalled', () => {
    try {
      localStorage.removeItem(SEEN_KEY)
    } catch {
      // Storage blocked: onboardingSeen() already treats that as seen.
    }
  })
}

type Step = { color: string; icon: LucideIcon; chipIcon: LucideIcon; chip: Key; title: Key; text: Key; tip?: Key }

// The colours the rider meets in the app: orange scan, green delivered, red failed.
const STEPS: Step[] = [
  { color: 'var(--brand)', icon: ScanLine, chipIcon: ScanLine, chip: 'scan', title: 'guide_scan_title', text: 'guide_scan_text', tip: 'guide_scan_tip' },
  { color: '#16a34a', icon: PackageCheck, chipIcon: PackageCheck, chip: 'action_delivered', title: 'guide_deliver_title', text: 'guide_deliver_text' },
  { color: '#dc2626', icon: PackageX, chipIcon: PackageX, chip: 'action_attempt_failed', title: 'guide_failed_title', text: 'guide_failed_text' },
  { color: '#2563eb', icon: Wallet, chipIcon: House, chip: 'nav_home', title: 'guide_day_title', text: 'guide_day_text' },
]

type Props = {
  onDone: () => void
  /** 'guide' skips the welcome — for opening the steps again from Profile. */
  startAt?: 'welcome' | 'guide'
}

/**
 * First open on a phone: a welcome screen with the rider video, then four
 * short steps on how the app works. Shown once per install; Profile opens the
 * steps again.
 */
export function Onboarding({ onDone, startAt = 'welcome' }: Props) {
  const [page, setPage] = useState(startAt === 'welcome' ? -1 : 0)

  const finish = () => {
    markSeen()
    onDone()
  }

  useStatusBarColor(page < 0 ? cssColor('--brand') : null)

  // Keep the page underneath from scrolling while this is up.
  useEffect(() => {
    const before = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = before
    }
  }, [])

  return (
    <div data-no-pull className='fixed inset-0 z-50'>
      {page < 0 ? <Welcome onStart={() => setPage(0)} /> : <Guide page={page} onPage={setPage} onDone={finish} />}
    </div>
  )
}

function Welcome({ onStart }: { onStart: () => void }) {
  const { t, toggle } = useI18n()

  return (
    <div className='flex h-full flex-col bg-brand px-6 pb-[calc(env(safe-area-inset-bottom)+20px)] pt-[calc(env(safe-area-inset-top)+14px)] text-ink'>
      <div className='flex shrink-0 items-center justify-between'>
        <span className='flex items-center gap-2 text-[15px] font-bold'>
          <Box className='h-7 w-7' strokeWidth={2.25} />
          KSA Express
        </span>
        <button
          type='button'
          onClick={toggle}
          className='flex h-10 items-center gap-1.5 rounded-full bg-ink/10 px-3.5 text-sm font-semibold transition-transform active:scale-95'
        >
          <Languages className='h-4 w-4' />
          {t('language')}
        </button>
      </div>

      <h1 className='mt-10 shrink-0 whitespace-pre-line text-[46px] font-extrabold leading-[1.02] tracking-tight duration-500 animate-in fade-in slide-in-from-bottom-3 motion-reduce:animate-none rtl:leading-[1.2] rtl:tracking-normal'>
        {t('welcome_title')}
      </h1>
      <p className='mt-4 max-w-[19rem] shrink-0 text-balance text-[16px] leading-snug text-ink/75'>{t('welcome_text')}</p>

      <div className='flex min-h-0 flex-1 items-center justify-center py-4'>
        <RiderVideo className='aspect-square h-full max-h-[400px] max-w-full object-contain' />
      </div>

      <button
        type='button'
        onClick={onStart}
        className='flex h-14 w-full shrink-0 items-center justify-center gap-2 rounded-full bg-ink text-[17px] font-semibold text-white transition-transform active:scale-[0.98]'
      >
        {t('get_started')}
        <ArrowRight className='h-5 w-5 rtl:rotate-180' />
      </button>
    </div>
  )
}

/**
 * The rider on the moped. Its background is see-through, which survives only
 * in HEVC on Safari and in VP9 WebM elsewhere, so each gets its own file. The
 * still stands in when motion is reduced or the video can't play.
 */
function RiderVideo({ className }: { className?: string }) {
  const ref = useRef<HTMLVideoElement>(null)
  const [still] = useState(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    const video = ref.current
    if (!video) return
    // React sets `muted` as a property only; phones want it before autoplay.
    video.muted = true
    video.play().catch(() => {
      // Low Power Mode or data saver: the poster stays.
    })
  }, [])

  if (still || failed) {
    return <img src={`${MEDIA}/rider-welcome-poster.webp`} alt='' className={className} />
  }

  return (
    <video
      ref={ref}
      src={isWebKit() ? `${MEDIA}/rider-welcome-hevc.mov` : `${MEDIA}/rider-welcome.webm`}
      poster={`${MEDIA}/rider-welcome-poster.webp`}
      autoPlay
      muted
      loop
      playsInline
      disablePictureInPicture
      preload='auto'
      aria-hidden
      onError={() => setFailed(true)}
      className={className}
    />
  )
}

function Guide({ page, onPage, onDone }: { page: number; onPage: (page: number) => void; onDone: () => void }) {
  const { t, lang } = useI18n()
  const touch = useRef<{ x: number; y: number } | null>(null)

  const step = STEPS[page]
  const last = page === STEPS.length - 1
  const Icon = step.icon
  const ChipIcon = step.chipIcon

  const next = () => (last ? onDone() : onPage(page + 1))
  const back = () => page > 0 && onPage(page - 1)

  // Swipe between steps. Forward is leftward in English, rightward in Arabic.
  const onTouchStart = (event: React.TouchEvent) => {
    touch.current = { x: event.touches[0].clientX, y: event.touches[0].clientY }
  }
  const onTouchEnd = (event: React.TouchEvent) => {
    const start = touch.current
    touch.current = null
    if (!start) return
    const dx = event.changedTouches[0].clientX - start.x
    const dy = event.changedTouches[0].clientY - start.y
    if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy)) return
    const forward = lang === 'ar' ? dx > 0 : dx < 0
    if (!forward) back()
    else if (!last) onPage(page + 1)
  }

  return (
    <div
      onTouchStart={onTouchStart}
      onTouchEnd={onTouchEnd}
      className='flex h-full flex-col bg-canvas px-6 pb-[calc(env(safe-area-inset-bottom)+20px)] pt-[calc(env(safe-area-inset-top)+14px)]'
    >
      <div className='flex h-10 shrink-0 items-center gap-4'>
        <div className='flex flex-1 gap-1.5' aria-hidden>
          {STEPS.map((_, i) => (
            <span key={i} className={cn('h-1 flex-1 rounded-full transition-colors', i <= page ? 'bg-foreground' : 'bg-foreground/15')} />
          ))}
        </div>
        <button type='button' onClick={onDone} className={cn('h-10 px-1 text-[15px] font-semibold text-muted-foreground', last && 'invisible')}>
          {t('guide_skip')}
        </button>
      </div>

      <div key={page} className='flex min-h-0 flex-1 flex-col duration-300 animate-in fade-in slide-in-from-bottom-2 motion-reduce:animate-none'>
        <div className='flex min-h-0 flex-1 items-center justify-center py-6'>
          <div className='relative flex aspect-square h-full max-h-[300px] max-w-full items-center justify-center rounded-[44px]' style={{ background: step.color }}>
            <Icon className='h-[36%] w-[36%] text-white' strokeWidth={1.5} />
            {/* The button the rider will tap, as the app labels it. */}
            <span className='absolute inset-x-4 bottom-5 flex justify-center'>
              <span className='inline-flex h-11 max-w-full items-center gap-2 rounded-full bg-white px-4 text-[15px] font-semibold' style={{ color: step.color }}>
                <ChipIcon className='h-5 w-5 shrink-0' />
                <span className='truncate'>{t(step.chip)}</span>
              </span>
            </span>
          </div>
        </div>

        {/* Room for the longest step, so the tile doesn't jump between steps. */}
        <div className='min-h-[15rem] shrink-0'>
          <p className='text-[13px] font-semibold text-muted-foreground'>{t('guide_step', { n: page + 1, total: STEPS.length })}</p>
          <h2 className='mt-1.5 text-[30px] font-extrabold leading-tight tracking-tight rtl:tracking-normal'>{t(step.title)}</h2>
          <p className='mt-2.5 text-[16px] leading-relaxed text-muted-foreground'>{t(step.text)}</p>
          {step.tip && (
            <p className='mt-4 flex items-start gap-2.5 rounded-2xl bg-surface p-3.5 text-[14px] leading-snug'>
              <Lightbulb className='mt-px h-[18px] w-[18px] shrink-0 text-brand' />
              {t(step.tip)}
            </p>
          )}
        </div>
      </div>

      <div className='mt-6 flex shrink-0 gap-3'>
        {page > 0 && (
          <button
            type='button'
            onClick={back}
            aria-label={t('guide_back')}
            className='flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-surface transition-transform active:scale-95'
          >
            <ArrowLeft className='h-5 w-5 rtl:rotate-180' />
          </button>
        )}
        <button
          type='button'
          onClick={next}
          className='flex h-14 flex-1 items-center justify-center gap-2 rounded-full bg-foreground text-[17px] font-semibold text-canvas transition-transform active:scale-[0.98]'
        >
          {last ? t('guide_done') : t('guide_next')}
          <ArrowRight className='h-5 w-5 rtl:rotate-180' />
        </button>
      </div>
    </div>
  )
}
