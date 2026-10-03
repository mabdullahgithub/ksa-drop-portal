import { useEffect, useRef } from 'react'

/**
 * Make the phone's Back (Android button, iPhone edge swipe) close the
 * scanner or a sheet instead of leaving the app.
 *
 * Each open overlay pushes a history entry. Back pops it and closes only the
 * top-most overlay, so with the update sheet open over the scanner, Back
 * closes the sheet and leaves the camera running. Closing with a button
 * removes the entry again without closing anything else.
 *
 * With nothing open, Back would close the app. The exit guard (one more
 * entry, under every overlay's) catches that Back and asks first.
 */

type Entry = { closedByBack: boolean; close: () => void }

type Guard = { armed: boolean; timer?: number; warn: () => void }

/** How long a second Back leaves the app after the first one asked. */
const EXIT_WINDOW_MS = 2500

const stack: Entry[] = []
let guard: Guard | null = null
let ownPops = 0
let listening = false

function listen() {
  if (listening) return
  listening = true
  window.addEventListener('popstate', () => {
    // Our own history.back() after a button close — nothing to close.
    if (ownPops > 0) {
      ownPops--
      return
    }
    const top = stack.pop()
    if (top) {
      top.closedByBack = true
      top.close()
      return
    }
    // Nothing was open: that Back used up the guard. Say so, and leave it
    // off for a moment — Back again now really leaves.
    if (guard?.armed) {
      guard.armed = false
      guard.warn()
      guard.timer = window.setTimeout(armGuard, EXIT_WINDOW_MS)
    }
  })
}

function armGuard() {
  if (!guard || guard.armed) return
  window.clearTimeout(guard.timer)
  guard.armed = true
  // After a reload the page is already sitting on its guard entry.
  if (!window.history.state?.riderGuard) window.history.pushState({ riderGuard: true }, '')
}

export function useBackToClose(open: boolean, close: () => void) {
  const closeRef = useRef(close)
  closeRef.current = close

  useEffect(() => {
    if (!open) return
    listen()

    // The guard's entry sits under every overlay's.
    armGuard()

    const entry: Entry = { closedByBack: false, close: () => closeRef.current() }
    stack.push(entry)
    window.history.pushState({ riderOverlay: stack.length }, '')

    return () => {
      const index = stack.indexOf(entry)
      if (index >= 0) stack.splice(index, 1)
      if (!entry.closedByBack) {
        ownPops++
        window.history.back()
      }
    }
  }, [open])
}

/**
 * Ask before Back closes the app. The first Back with nothing open calls
 * `warn` ("press Back again to close") instead of leaving; a second one
 * within a moment leaves. A web app can't close itself, so there is no
 * "Close" button to offer: the second Back is the yes.
 *
 * Only where Back closes an app (Android). On iPhone nothing does, and an
 * extra history entry would only give the edge swipe something to slide.
 */
export function useExitGuard(enabled: boolean, warn: () => void) {
  const warnRef = useRef(warn)
  warnRef.current = warn

  useEffect(() => {
    if (!enabled) return
    listen()

    const mine: Guard = { armed: false, warn: () => warnRef.current() }
    guard = mine
    armGuard()

    // The entry stays behind: with no guard it's just one Back that does nothing.
    return () => {
      window.clearTimeout(mine.timer)
      if (guard === mine) guard = null
    }
  }, [enabled])
}
