import { useState } from 'react'
import { CirclePlay, Clock, Minus, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

const SKIP_KEY = 'guide_video_skipped'
const REMIND_AT_KEY = 'guide_video_remind_at'
const REMIND_DELAY_MS = 24 * 60 * 60 * 1000

const VIDEO_SRC =
  'https://www.youtube.com/embed/Y_Xs1DzSfsg?si=4KfeuhyHqoFfxcUZ'

/* Specular rim light — the inset highlight that gives liquid glass its depth */
const glassRim =
  '[box-shadow:inset_0_1px_1px_rgba(255,255,255,0.45),inset_0_-1px_1px_rgba(255,255,255,0.12)]'

function shouldShow() {
  if (typeof window === 'undefined') return false
  if (localStorage.getItem(SKIP_KEY) === '1') return false
  const remindAt = Number(localStorage.getItem(REMIND_AT_KEY) ?? 0)
  return Date.now() >= remindAt
}

export function GuideVideoWidget() {
  const [visible, setVisible] = useState(shouldShow)
  const [minimized, setMinimized] = useState(false)

  if (!visible) return null

  const skip = () => {
    localStorage.setItem(SKIP_KEY, '1')
    setVisible(false)
  }

  const remindLater = () => {
    localStorage.setItem(REMIND_AT_KEY, String(Date.now() + REMIND_DELAY_MS))
    setVisible(false)
  }

  if (minimized) {
    return (
      <div className='fixed end-4 bottom-4 z-50'>
        <Button
          onClick={() => setMinimized(false)}
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
              onClick={() => setMinimized(true)}
            >
              <span className='sr-only'>Minimize</span>
              <Minus className='size-4' />
            </Button>
            <Button
              variant='ghost'
              size='icon'
              className='size-7 rounded-full hover:bg-black/5 dark:hover:bg-white/10'
              onClick={skip}
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
            onClick={remindLater}
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
            onClick={skip}
          >
            Skip
          </Button>
        </div>
      </div>
    </div>
  )
}
