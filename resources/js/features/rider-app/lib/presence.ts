import { useEffect } from 'react'
import { api } from '../api'

/** How often the open app checks in. The server counts one for two minutes. */
const EVERY = 60_000

/**
 * Tells the office this rider has the app open — the green dot on the
 * Riders page. Only while the app is on screen: a phone put away stops
 * checking in and drops off by itself.
 */
export function usePresence() {
  useEffect(() => {
    let timer: number | undefined

    const checkIn = () =>
      api.post('/rider/api/presence').catch(() => {
        // No signal: the next check-in will do.
      })
    const start = () => {
      checkIn()
      window.clearInterval(timer)
      timer = window.setInterval(checkIn, EVERY)
    }
    const stop = () => window.clearInterval(timer)
    const onVisibility = () => (document.visibilityState === 'visible' ? start() : stop())

    if (document.visibilityState === 'visible') start()
    document.addEventListener('visibilitychange', onVisibility)
    return () => {
      stop()
      document.removeEventListener('visibilitychange', onVisibility)
    }
  }, [])
}
