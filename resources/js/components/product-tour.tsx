import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react'
import { createPortal } from 'react-dom'
import { Button } from '@/components/ui/button'

/**
 * A light, dependency-free guided tour. Each step points at an element marked
 * with a `data-tour` attribute; a step whose element is not on the page is
 * skipped, so a tour never stalls on something the user cannot see.
 */
export type TourStep = {
  /** Value of the target's `data-tour` attribute. */
  target: string
  title: string
  body: React.ReactNode
}

type Props = {
  steps: TourStep[]
  open: boolean
  onClose: () => void
}

const PADDING = 6
const CARD_WIDTH = 320
const GAP = 12

type Rect = { top: number; left: number; width: number; height: number }

function findTarget(step: TourStep): HTMLElement | null {
  const el = document.querySelector<HTMLElement>(`[data-tour="${step.target}"]`)
  if (!el) return null
  const rect = el.getBoundingClientRect()
  return rect.width > 0 && rect.height > 0 ? el : null
}

export function ProductTour({ steps, open, onClose }: Props) {
  // Steps whose target is on the page, worked out when the tour opens.
  const [available, setAvailable] = useState<TourStep[]>([])
  const [index, setIndex] = useState(0)
  const [rect, setRect] = useState<Rect | null>(null)

  useEffect(() => {
    if (!open) return
    const present = steps.filter((step) => findTarget(step))
    if (present.length === 0) {
      onClose()
      return
    }
    setAvailable(present)
    setIndex(0)
  }, [open, steps, onClose])

  const step = available[index]

  const measure = useCallback(() => {
    if (!step) return
    const el = findTarget(step)
    if (!el) {
      setRect(null)
      return
    }
    const r = el.getBoundingClientRect()
    setRect({ top: r.top, left: r.left, width: r.width, height: r.height })
  }, [step])

  // Bring the step's element into view, then follow it as the page moves.
  useLayoutEffect(() => {
    if (!open || !step) return
    findTarget(step)?.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' })
    measure()
    const timer = window.setTimeout(measure, 350)
    window.addEventListener('resize', measure)
    window.addEventListener('scroll', measure, true)
    return () => {
      window.clearTimeout(timer)
      window.removeEventListener('resize', measure)
      window.removeEventListener('scroll', measure, true)
    }
  }, [open, step, measure])

  const isLast = index === available.length - 1
  const next = useCallback(() => (isLast ? onClose() : setIndex((i) => i + 1)), [isLast, onClose])
  const back = useCallback(() => setIndex((i) => Math.max(0, i - 1)), [])

  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      else if (e.key === 'ArrowRight' || e.key === 'Enter') next()
      else if (e.key === 'ArrowLeft') back()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open, next, back, onClose])

  // Below the element when there is room, otherwise above; kept on screen.
  const cardStyle = useMemo<React.CSSProperties>(() => {
    if (!rect) return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)', width: CARD_WIDTH }
    const vw = window.innerWidth
    const vh = window.innerHeight
    const width = Math.min(CARD_WIDTH, vw - 24)
    const left = Math.min(Math.max(12, rect.left + rect.width / 2 - width / 2), vw - width - 12)
    const below = rect.top + rect.height + PADDING + GAP
    const fitsBelow = below + 190 < vh
    return fitsBelow
      ? { top: below, left, width }
      : { top: Math.max(12, rect.top - PADDING - GAP), left, width, transform: 'translateY(-100%)' }
  }, [rect])

  if (!open || !step) return null

  return createPortal(
    <div className='fixed inset-0 z-[100]' role='dialog' aria-modal='true' aria-label={step.title}>
      {/* Blocks the page while the tour runs. */}
      <div className='absolute inset-0' onClick={(e) => e.stopPropagation()} />

      {rect ? (
        <div
          className='pointer-events-none absolute rounded-lg ring-2 ring-primary transition-all duration-300 ease-out'
          style={{
            top: rect.top - PADDING,
            left: rect.left - PADDING,
            width: rect.width + PADDING * 2,
            height: rect.height + PADDING * 2,
            boxShadow: '0 0 0 9999px rgba(0, 0, 0, 0.55)',
          }}
        />
      ) : (
        <div className='absolute inset-0 bg-black/55' />
      )}

      <div
        className='absolute rounded-lg border bg-popover p-4 text-popover-foreground shadow-xl transition-all duration-300 ease-out'
        style={cardStyle}
      >
        <div className='mb-1 flex items-center justify-between gap-2'>
          <p className='text-sm font-semibold'>{step.title}</p>
          <span className='shrink-0 text-xs text-muted-foreground tabular-nums'>
            {index + 1} / {available.length}
          </span>
        </div>
        <div className='text-sm text-muted-foreground'>{step.body}</div>

        <div className='mt-4 flex items-center justify-between gap-2'>
          <Button variant='ghost' size='sm' className='h-8 px-2 text-xs text-muted-foreground' onClick={onClose}>
            Skip tour
          </Button>
          <div className='flex gap-2'>
            {index > 0 && (
              <Button variant='outline' size='sm' className='h-8 text-xs' onClick={back}>
                Back
              </Button>
            )}
            <Button size='sm' className='h-8 text-xs' onClick={next}>
              {isLast ? 'Done' : 'Next'}
            </Button>
          </div>
        </div>
      </div>
    </div>,
    document.body
  )
}

/**
 * Whether a tour has been seen, remembered per user and per tour version, so
 * bumping the version shows it again after the page changes.
 */
export function useTourSeen(key: string) {
  const storageKey = `tour_seen:${key}`
  const [seen, setSeen] = useState(() => {
    if (typeof window === 'undefined') return true
    try {
      return localStorage.getItem(storageKey) === '1'
    } catch {
      return true
    }
  })

  const markSeen = useCallback(() => {
    try {
      localStorage.setItem(storageKey, '1')
    } catch {
      // Storage unavailable: the tour simply shows again next time.
    }
    setSeen(true)
  }, [storageKey])

  return { seen, markSeen }
}
