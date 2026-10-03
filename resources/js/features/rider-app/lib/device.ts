/**
 * Phone helpers for the rider app: install state, platform, photos,
 * location, ids.
 */

export function isStandalone(): boolean {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    (navigator as unknown as { standalone?: boolean }).standalone === true
  )
}

export function platform(): 'android' | 'ios' | 'other' {
  const ua = navigator.userAgent
  if (/android/i.test(ua)) return 'android'
  // iPadOS reports itself as a Mac with touch.
  if (/iphone|ipad|ipod/i.test(ua) || (/macintosh/i.test(ua) && navigator.maxTouchPoints > 1)) return 'ios'
  return 'other'
}

/** Safari's engine: every iPhone browser, and Safari on a Mac. */
export function isWebKit(): boolean {
  const ua = navigator.userAgent
  return platform() === 'ios' || (/Safari\//.test(ua) && !/Chrome|Chromium|Edg|Android/.test(ua))
}

/** Facebook, Instagram, Snapchat, TikTok, Line… — their built-in browsers can't install apps. */
export function isInAppBrowser(): boolean {
  return /FBAN|FBAV|FB_IAB|Instagram|Snapchat|musical_ly|BytedanceWebview|Line\/|; wv\)/i.test(navigator.userAgent)
}

export type InstallPromptEvent = Event & {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

let installPrompt: InstallPromptEvent | null = null
const installListeners = new Set<(event: InstallPromptEvent | null) => void>()

/**
 * Chrome fires beforeinstallprompt once, often before React has mounted, so
 * it's caught at startup (rider-app.tsx) and handed to whoever subscribes.
 */
export function captureInstallPrompt() {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    installPrompt = event as InstallPromptEvent
    installListeners.forEach((listener) => listener(installPrompt))
  })
}

export function onInstallPrompt(listener: (event: InstallPromptEvent | null) => void): () => void {
  listener(installPrompt)
  installListeners.add(listener)
  return () => installListeners.delete(listener)
}

export function consumeInstallPrompt() {
  installPrompt = null
  installListeners.forEach((listener) => listener(null))
}

export { uuid } from '@/lib/uuid'

export function vibrate(pattern: number | number[] = 60) {
  try {
    navigator.vibrate?.(pattern)
  } catch {
    // iPhone has no vibration API.
  }
}

export type Position = { lat: number; lng: number; accuracy: number }

/**
 * Why there's no location: the rider (or an earlier "Block") refused the
 * app, or the phone couldn't say where it is — location switched off, no
 * signal, or this phone has none.
 */
export type PositionProblem = 'blocked' | 'unavailable'

const problemOf = (error: GeolocationPositionError): PositionProblem => (error.code === error.PERMISSION_DENIED ? 'blocked' : 'unavailable')

const toPosition = (p: GeolocationPosition): Position => ({
  lat: p.coords.latitude,
  lng: p.coords.longitude,
  accuracy: Math.round(p.coords.accuracy),
})

/**
 * Keep a fresh location while the update screen is open, without ever
 * making the rider wait for it. Opening it is also what makes the phone ask
 * for permission the first time. Returns a stop function.
 */
export function watchPosition(onPosition: (position: Position) => void, onProblem?: (problem: PositionProblem) => void): () => void {
  if (!('geolocation' in navigator)) {
    onProblem?.('unavailable')
    return () => {}
  }

  const id = navigator.geolocation.watchPosition(
    (p) => onPosition(toPosition(p)),
    // The update goes through without it; the form asks the rider to turn it on.
    (error) => onProblem?.(problemOf(error)),
    { enableHighAccuracy: true, maximumAge: 60_000, timeout: 20_000 }
  )

  return () => navigator.geolocation.clearWatch(id)
}

/**
 * Ask for the location now, from the rider's tap on "Turn on location":
 * the phone shows its permission question if it hasn't been answered yet.
 */
export function requestPosition(timeoutMs = 15_000): Promise<Position | PositionProblem> {
  if (!('geolocation' in navigator)) return Promise.resolve('unavailable')

  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (p) => resolve(toPosition(p)),
      (error) => resolve(problemOf(error)),
      { enableHighAccuracy: true, maximumAge: 60_000, timeout: timeoutMs }
    )
  })
}

/**
 * One try for where the phone is, for an update about to be sent. Never
 * rejects and never waits long: null when location is off, refused or slow.
 */
export function currentPosition(timeoutMs = 4000): Promise<Position | null> {
  if (!('geolocation' in navigator)) return Promise.resolve(null)

  return new Promise((resolve) => {
    const giveUp = window.setTimeout(() => resolve(null), timeoutMs + 500)
    const done = (position: Position | null) => {
      window.clearTimeout(giveUp)
      resolve(position)
    }

    navigator.geolocation.getCurrentPosition(
      (p) => done(toPosition(p)),
      () => done(null),
      { enableHighAccuracy: true, maximumAge: 60_000, timeout: timeoutMs }
    )
  })
}

/** wa.me wants digits only, with the country code. */
export function whatsAppNumber(phone: string): string {
  const digits = phone.replace(/\D/g, '')
  if (digits.startsWith('966')) return digits
  if (digits.startsWith('05')) return '966' + digits.slice(1)
  if (digits.startsWith('5') && digits.length === 9) return '966' + digits
  return digits
}
