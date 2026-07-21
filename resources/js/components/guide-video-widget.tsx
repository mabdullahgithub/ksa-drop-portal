import { useState } from 'react'
import { CirclePlay, Clock, Minus, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { ConfirmDialog } from '@/components/confirm-dialog'

/*
 * Pages don't use Inertia's persistent-layout pattern (each Page wraps its own
 * <AuthenticatedLayout>), so this component remounts on every navigation.
 * Minimized/hidden state is therefore kept in localStorage, not just React
 * state, so it survives across page changes.
 */
const HIDE_UNTIL_KEY = 'guide_video_hide_until'
const MINIMIZED_KEY = 'guide_video_minimized'
const HIDE_DELAY_MS = 24 * 60 * 60 * 1000

const VIDEO_SRC =
  'https://www.youtube.com/embed/Y_Xs1DzSfsg?si=4KfeuhyHqoFfxcUZ'

/* Specular rim light — the inset highlight that gives liquid glass its depth */
const glassRim =
  '[box-shadow:inset_0_1px_1px_rgba(255,255,255,0.45),inset_0_-1px_1px_rgba(255,255,255,0.12)]'

function shouldShow() {
  if (typeof window === 'undefined') return false
  const hideUntil = Number(localStorage.getItem(HIDE_UNTIL_KEY) ?? 0)
  return Date.now() >= hideUntil
}

function wasMinimized() {
  if (typeof window === 'undefined') return false
  return localStorage.getItem(MINIMIZED_KEY) === '1'
}

export function GuideVideoWidget() {
  const [visible, setVisible] = useState(shouldShow)
  const [minimized, setMinimized] = useState(wasMinimized)
  const [confirmSkipOpen, setConfirmSkipOpen] = useState(false)

  if (!visible) return null

  const hideFor24Hours = () => {
    localStorage.setItem(HIDE_UNTIL_KEY, String(Date.now() + HIDE_DELAY_MS))
    localStorage.removeItem(MINIMIZED_KEY)
    setVisible(false)
  }

  const minimize = () => {
    localStorage.setItem(MINIMIZED_KEY, '1')
    setMinimized(true)
  }

  const restore = () => {
    localStorage.removeItem(MINIMIZED_KEY)
    setMinimized(false)
  }

  if (minimized) {
    return (
      <div className='fixed end-4 bottom-4 z-50'>
        <Button
          onClick={restore}
          className={cn(
            'gap-2 rounded-full border border-white/40 dark:border-white/15',
            'ring-1 ring-black/10 dark:ring-white/10',
            'bg-primary/90 text-primary-foreground backdrop-blur-2xl backdrop-saturate-200',
            'shadow-none hover:bg-primary',
            glassRim
          )}
        >
          <CirclePlay className='size-4' />
          Watch guide
        </Button>
      </div>
    )
  }

  return (
    <>
      <div className='fixed end-4 bottom-4 z-50 w-[min(24rem,calc(100vw-2rem))]'>
        <div
          role='dialog'
          aria-label='Portal guide video'
          className={cn(
            'overflow-hidden rounded-3xl',
            'border border-black/[0.08] dark:border-white/10',
            'ring-1 ring-black/[0.04] dark:ring-white/10',
            'bg-white/65 dark:bg-white/[0.06]',
            'backdrop-blur-3xl backdrop-saturate-200',
            glassRim,
            'animate-in fade-in slide-in-from-bottom-4 duration-300'
          )}
        >
          <div
            className={cn(
              'flex items-center justify-between gap-2 px-4 py-3',
              'border-b border-black/[0.06] dark:border-white/10',
              'bg-gradient-to-b from-white/50 to-transparent dark:from-white/[0.07]'
            )}
          >
            <div className='flex items-center gap-2.5'>
              <span
                className={cn(
                  'flex size-8 items-center justify-center rounded-full',
                  'border border-black/[0.06] bg-primary/10 backdrop-blur-xl dark:border-white/15 dark:bg-white/10',
                  glassRim
                )}
              >
                <CirclePlay className='size-4 text-primary' />
              </span>
              <div className='leading-tight'>
                <p className='text-sm font-semibold'>Getting started guide</p>
                <p className='text-[11px] text-muted-foreground'>
                  A quick tour of the portal
                </p>
              </div>
            </div>
            <div className='flex items-center gap-1'>
              <Button
                variant='ghost'
                size='icon'
                className='size-7 rounded-full hover:bg-black/5 dark:hover:bg-white/10'
                onClick={minimize}
              >
                <span className='sr-only'>Minimize</span>
                <Minus className='size-4' />
              </Button>
              <Button
                variant='ghost'
                size='icon'
                className='size-7 rounded-full hover:bg-black/5 dark:hover:bg-white/10'
                onClick={() => setConfirmSkipOpen(true)}
              >
                <span className='sr-only'>Close</span>
                <X className='size-4' />
              </Button>
            </div>
          </div>

          <div className='p-3 pb-0'>
            <div
              className={cn(
                'aspect-video w-full overflow-hidden rounded-2xl bg-black/60',
                'border border-black/[0.08] dark:border-white/10'
              )}
            >
              <iframe
                className='size-full'
                src={VIDEO_SRC}
                title='YouTube video player'
                allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
                referrerPolicy='strict-origin-when-cross-origin'
                allowFullScreen
              />
            </div>
          </div>

          <div className='flex items-center justify-end gap-2 px-3 py-3'>
            <Button
              variant='ghost'
              size='sm'
              className='gap-1.5 rounded-full hover:bg-black/5 dark:hover:bg-white/10'
              onClick={hideFor24Hours}
            >
              <Clock className='size-3.5' />
              Remind me later
            </Button>
            <Button
              size='sm'
              className={cn(
                'rounded-full border border-white/40 dark:border-white/15',
                'bg-primary/90 text-primary-foreground backdrop-blur-xl backdrop-saturate-200',
                'shadow-none hover:bg-primary',
                glassRim
              )}
              onClick={() => setConfirmSkipOpen(true)}
            >
              Skip
            </Button>
          </div>
        </div>
      </div>

      <ConfirmDialog
        open={confirmSkipOpen}
        onOpenChange={setConfirmSkipOpen}
        title='Skip the getting started guide?'
        desc="It'll be hidden for the next 24 hours. You can bring it back anytime before then from wherever this widget reappears."
        cancelBtnText='Cancel'
        confirmText='Skip for 24 hours'
        handleConfirm={() => {
          setConfirmSkipOpen(false)
          hideFor24Hours()
        }}
      />
    </>
  )
}
