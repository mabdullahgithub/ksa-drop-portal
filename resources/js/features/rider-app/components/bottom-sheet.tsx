import { useEffect, useRef, useState } from 'react'
import { cn } from '@/lib/utils'
import { Sheet, SheetContent } from '@/components/ui/sheet'

/** How far down (px) the sheet has to be dragged before letting go closes it. */
const CLOSE_AFTER = 120
/** A quick flick closes it from less far: px per ms, over at least FLICK_MIN px. */
const FLICK_SPEED = 0.5
const FLICK_MIN = 40

/** Something under the finger is scrolled down: a drag there scrolls it back up instead. */
function scrolledDown(target: Element | null, sheet: Element): boolean {
  for (let element = target; element && element !== sheet; element = element.parentElement) {
    if (element.scrollTop > 0) return true
  }
  return sheet.scrollTop > 0
}

/**
 * Drag the sheet down to close it, like any phone sheet. It follows the
 * finger; let go far enough down (or flick) and it closes, otherwise it
 * springs back. Returns the ref for the sheet's element.
 */
function useSwipeToClose(close: () => void, enabled: boolean) {
  const [sheet, setSheet] = useState<HTMLElement | null>(null)
  const closeRef = useRef(close)
  closeRef.current = close

  useEffect(() => {
    if (!sheet || !enabled) return

    let start: { x: number; y: number; at: number } | null = null
    let dragging = false
    let distance = 0

    const springBack = () => {
      sheet.style.transition = 'transform 200ms ease-out'
      sheet.style.transform = ''
    }

    const onStart = (event: TouchEvent) => {
      if (event.touches.length > 1 || scrolledDown(event.target as Element | null, sheet)) {
        start = null
        return
      }
      start = { x: event.touches[0].clientX, y: event.touches[0].clientY, at: event.timeStamp }
    }

    const onMove = (event: TouchEvent) => {
      if (!start) return
      const dx = event.touches[0].clientX - start.x
      const dy = event.touches[0].clientY - start.y

      if (!dragging) {
        // Scrolling the content, or moving sideways: not a drag on the sheet.
        if (dy < -8 || Math.abs(dx) > Math.max(dy, 8)) {
          start = null
          return
        }
        if (dy < 8) return
        dragging = true
        sheet.style.transition = 'none'
      }

      if (event.cancelable) event.preventDefault()
      distance = Math.max(0, dy)
      sheet.style.transform = `translateY(${distance}px)`
    }

    const onEnd = (event: TouchEvent) => {
      if (!start || !dragging) {
        start = null
        return
      }
      const speed = distance / Math.max(1, event.timeStamp - start.at)
      const shouldClose = distance > CLOSE_AFTER || (distance > FLICK_MIN && speed > FLICK_SPEED)
      start = null
      dragging = false

      if (!shouldClose) {
        springBack()
        return
      }

      // The closing slide carries on from where the finger left it.
      closeRef.current()
      // Still open a moment later: whoever owns the sheet said no.
      window.setTimeout(() => sheet.dataset.state === 'open' && springBack(), 150)
    }

    const onCancel = () => {
      if (dragging) springBack()
      start = null
      dragging = false
    }

    sheet.addEventListener('touchstart', onStart, { passive: true })
    sheet.addEventListener('touchmove', onMove, { passive: false })
    sheet.addEventListener('touchend', onEnd)
    sheet.addEventListener('touchcancel', onCancel)
    return () => {
      sheet.removeEventListener('touchstart', onStart)
      sheet.removeEventListener('touchmove', onMove)
      sheet.removeEventListener('touchend', onEnd)
      sheet.removeEventListener('touchcancel', onCancel)
    }
  }, [sheet, enabled])

  return setSheet
}

type Props = {
  open: boolean
  onClose: () => void
  /** Something is being sent: the sheet stays until it's done. */
  locked?: boolean
  className?: string
  children: React.ReactNode
}

/**
 * Every sheet in the rider app that comes up from the bottom. Closes with
 * its ✕, a tap outside, the phone's Back (the opener's useBackToClose), or
 * by dragging it down.
 */
export function BottomSheet({ open, onClose, locked = false, className, children }: Props) {
  const swipe = useSwipeToClose(onClose, !locked)

  return (
    <Sheet open={open} onOpenChange={(next) => !next && !locked && onClose()}>
      <SheetContent
        ref={swipe}
        data-no-pull
        side='bottom'
        className={cn('gap-0 rounded-t-[32px] p-0', className)}
        // Don't focus the first control on open: it would ring the close
        // button, or pop the keyboard up over the sheet.
        onOpenAutoFocus={(event) => event.preventDefault()}
      >
        {children}
      </SheetContent>
    </Sheet>
  )
}
