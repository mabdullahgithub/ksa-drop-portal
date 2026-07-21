import { useState } from 'react'
import { CirclePlay, Minus } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

/*
 * Pages don't use Inertia's persistent-layout pattern (each Page wraps its own
 * <AuthenticatedLayout>), so this component remounts on every navigation.
 * Minimized state is therefore kept in localStorage, not just React state,
 * so it survives across page changes. The video can be minimized but never
 * fully dismissed/skipped.
 */
const MINIMIZED_KEY = 'guide_video_minimized'

const VIDEO_SRC =
  'https://www.youtube.com/embed/Y_Xs1DzSfsg?si=4KfeuhyHqoFfxcUZ'

/* Specular rim light — the inset highlight that gives liquid glass its depth */
const glassRim =
  '[box-shadow:inset_0_1px_1px_rgba(255,255,255,0.45),inset_0_-1px_1px_rgba(255,255,255,0.12)]'

function wasMinimized() {
  if (typeof window === 'undefined') return false
  return localStorage.getItem(MINIMIZED_KEY) === '1'
}

export function GuideVideoWidget() {
  const [minimized, setMinimized] = useState(wasMinimized)

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
          </div>
        </div>

        <div className='p-3'>
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
      </div>
    </div>
  )
}
