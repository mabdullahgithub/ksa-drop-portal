import { useEffect, useRef, useState } from 'react'
import { RefreshCw } from 'lucide-react'
import { cn } from '@/lib/utils'
import { vibrate } from './device'

/** How far the circle has to come down (px) before letting go refreshes. */
const THRESHOLD = 72
const MAX = 110
/** Finger travel per pixel of pull: the pull feels heavier than a scroll. */
const RESISTANCE = 0.5

export type PullState = { pull: number; dragging: boolean; refreshing: boolean }

/** The page, or a list the finger is on, is scrolled down: a drag there scrolls back up instead. */
function scrolledDown(target: Element | null): boolean {
  for (let element = target; element; element = element.parentElement) {
    if (element.scrollTop > 0) return true
  }
  return window.scrollY > 0
}

/**
 * Pull down at the top of the page to refresh, like any phone app. The page
 * itself has overscroll switched off (resources/css/rider.css), and an
 * installed app gets no browser pull-to-refresh, so this is our own.
 *
 * Touches inside [data-no-pull] (full-screen overlays) are left alone.
 */
export function usePullToRefresh(onRefresh: () => Promise<unknown>, enabled: boolean): PullState {
  const [pull, setPull] = useState(0)
  const [dragging, setDragging] = useState(false)
  const [refreshing, setRefreshing] = useState(false)
  const refreshRef = useRef(onRefresh)
  refreshRef.current = onRefresh

  useEffect(() => {
    if (!enabled || refreshing) return

    let start: { x: number; y: number } | null = null
    let pulling = false
    let distance = 0

    const reset = () => {
      start = null
      pulling = false
      distance = 0
      setDragging(false)
    }

    const onStart = (event: TouchEvent) => {
      const target = event.target as Element | null
      if (event.touches.length > 1 || target?.closest('[data-no-pull]') || scrolledDown(target)) {
        start = null
        return
      }
      start = { x: event.touches[0].clientX, y: event.touches[0].clientY }
    }

    const onMove = (event: TouchEvent) => {
      if (!start) return
      const dx = event.touches[0].clientX - start.x
      const dy = event.touches[0].clientY - start.y

      if (!pulling) {
        // Scrolling up or sideways: not a pull.
        if (dy < -8 || Math.abs(dx) > Math.max(dy, 8)) {
          start = null
          return
        }
        if (dy < 8) return
        pulling = true
        setDragging(true)
      }

      event.preventDefault()
      const next = Math.min(MAX, Math.max(0, dy) * RESISTANCE)
      if (distance < THRESHOLD && next >= THRESHOLD) vibrate(10)
      distance = next
      setPull(next)
    }

    const onEnd = async () => {
      const release = pulling && distance >= THRESHOLD
      reset()
      if (!release) {
        setPull(0)
        return
      }

      setPull(THRESHOLD)
      setRefreshing(true)
      // Stay up long enough to be seen, even when the reload is instant.
      await Promise.allSettled([refreshRef.current(), new Promise((resolve) => setTimeout(resolve, 600))])
      setRefreshing(false)
      setPull(0)
    }

    const onCancel = () => {
      reset()
      setPull(0)
    }

    window.addEventListener('touchstart', onStart, { passive: true })
    window.addEventListener('touchmove', onMove, { passive: false })
    window.addEventListener('touchend', onEnd)
    window.addEventListener('touchcancel', onCancel)
    return () => {
      window.removeEventListener('touchstart', onStart)
      window.removeEventListener('touchmove', onMove)
      window.removeEventListener('touchend', onEnd)
      window.removeEventListener('touchcancel', onCancel)
    }
  }, [enabled, refreshing])

  return { pull, dragging, refreshing }
}

/** The circle that comes down while pulling. */
export function PullIndicator({ pull, dragging, refreshing }: PullState) {
  const progress = Math.min(1, pull / THRESHOLD)

  return (
    <div
      aria-hidden
      className='pointer-events-none fixed inset-x-0 z-40 flex justify-center'
      style={{
        top: 'calc(env(safe-area-inset-top) + 8px)',
        transform: `translateY(${pull - 48}px)`,
        // Fades as it slides back, so it never rests behind the status bar.
        opacity: pull > 0 || refreshing ? 1 : 0,
        transition: dragging ? 'none' : 'transform 200ms ease-out, opacity 200ms ease-out',
      }}
    >
      <span className='flex h-10 w-10 items-center justify-center rounded-full bg-surface shadow-md'>
        <RefreshCw
          className={cn('h-5 w-5 text-brand', refreshing && 'animate-spin')}
          style={refreshing ? undefined : { transform: `rotate(${progress * 270}deg)`, opacity: 0.35 + progress * 0.65 }}
        />
      </span>
    </div>
  )
}
