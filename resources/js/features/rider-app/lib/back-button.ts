import { useEffect, useRef } from 'react'

/**
 * Make the phone's Back (Android button, iPhone edge swipe) close the
 * scanner or a sheet instead of leaving the app.
 *
 * Each open overlay pushes a history entry. Back pops it and closes only the
 * top-most overlay, so with the update sheet open over the scanner, Back
 * closes the sheet and leaves the camera running. Closing with a button
 * removes the entry again without closing anything else.
 */

type Entry = { closedByBack: boolean; close: () => void }

const stack: Entry[] = []
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
    }
  })
}

export function useBackToClose(open: boolean, close: () => void) {
  const closeRef = useRef(close)
  closeRef.current = close

  useEffect(() => {
    if (!open) return
    listen()

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
