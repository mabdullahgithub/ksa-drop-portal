import { useState } from 'react'
import { CirclePlay, Clock, Minus, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

const SKIP_KEY = 'guide_video_skipped'
const REMIND_AT_KEY = 'guide_video_remind_at'
const REMIND_DELAY_MS = 24 * 60 * 60 * 1000

const VIDEO_SRC =
  'https://www.youtube.com/embed/Y_Xs1DzSfsg?si=4KfeuhyHqoFfxcUZ'

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
          className='gap-2 rounded-full shadow-lg'
        >
          <CirclePlay className='size-4' />
          Watch guide
        </Button>
      </div>
    )
  }

  return (
    <div
      role='dialog'
      aria-label='Portal guide video'
      className={cn(
        'fixed end-4 bottom-4 z-50 w-[min(24rem,calc(100vw-2rem))]',
        'overflow-hidden rounded-xl border bg-card text-card-foreground shadow-2xl',
        'animate-in fade-in slide-in-from-bottom-4 duration-300'
      )}
    >
      <div className='flex items-center justify-between gap-2 border-b px-4 py-2.5'>
        <div className='flex items-center gap-2'>
          <CirclePlay className='size-4 text-primary' />
          <span className='text-sm font-semibold'>Getting started guide</span>
        </div>
        <div className='flex items-center gap-1'>
          <Button
            variant='ghost'
            size='icon'
            className='size-7'
            onClick={() => setMinimized(true)}
          >
            <span className='sr-only'>Minimize</span>
            <Minus className='size-4' />
          </Button>
          <Button variant='ghost' size='icon' className='size-7' onClick={skip}>
            <span className='sr-only'>Close</span>
            <X className='size-4' />
          </Button>
        </div>
      </div>

      <div className='aspect-video w-full bg-black'>
        <iframe
          className='size-full'
          src={VIDEO_SRC}
          title='YouTube video player'
          allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
          referrerPolicy='strict-origin-when-cross-origin'
          allowFullScreen
        />
      </div>

      <div className='flex items-center justify-between gap-2 px-4 py-3'>
        <p className='text-xs text-muted-foreground'>
          A quick tour of the portal
        </p>
        <div className='flex items-center gap-2'>
          <Button variant='ghost' size='sm' className='gap-1.5' onClick={remindLater}>
            <Clock className='size-3.5' />
            Remind me later
          </Button>
          <Button variant='outline' size='sm' onClick={skip}>
            Skip
          </Button>
        </div>
      </div>
    </div>
  )
}
