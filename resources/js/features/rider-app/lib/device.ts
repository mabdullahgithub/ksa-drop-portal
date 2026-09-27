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

export function uuid(): string {
  const c = globalThis.crypto as Crypto & { randomUUID?: () => string }
  if (typeof c.randomUUID === 'function') {
    return c.randomUUID()
  }
  // Older iOS: RFC 4122 v4 from getRandomValues.
  const bytes = c.getRandomValues(new Uint8Array(16))
  bytes[6] = (bytes[6] & 0x0f) | 0x40
  bytes[8] = (bytes[8] & 0x3f) | 0x80
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

export function vibrate(pattern: number | number[] = 60) {
  try {
    navigator.vibrate?.(pattern)
  } catch {
    // iPhone has no vibration API.
  }
}

export type Position = { lat: number; lng: number; accuracy: number }

/**
 * Keep a fresh location while the update screen is open, without ever
 * making the rider wait for it. Returns a stop function.
 */
export function watchPosition(onPosition: (position: Position) => void): () => void {
  if (!('geolocation' in navigator)) return () => {}

  const id = navigator.geolocation.watchPosition(
    (p) => onPosition({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) }),
    () => {
      // Denied or unavailable — the update goes through without it.
    },
    { enableHighAccuracy: true, maximumAge: 60_000, timeout: 20_000 }
  )

  return () => navigator.geolocation.clearWatch(id)
}

/** wa.me wants digits only, with the country code. */
export function whatsAppNumber(phone: string): string {
  const digits = phone.replace(/\D/g, '')
  if (digits.startsWith('966')) return digits
  if (digits.startsWith('05')) return '966' + digits.slice(1)
  if (digits.startsWith('5') && digits.length === 9) return '966' + digits
  return digits
}
