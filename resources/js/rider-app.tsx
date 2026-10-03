import '../css/rider.css'

import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RiderApp } from '@/features/rider-app/app'
import { captureInstallPrompt, wantsSolidGlass } from '@/features/rider-app/lib/device'
import { resetOnboardingOnInstall } from '@/features/rider-app/screens/onboarding'
import type { RiderBoot } from '@/features/rider-app/types'

/**
 * KSA Express rider app — a separate, small React tree from the portal,
 * installed on riders' phones as a web app. Served by
 * RiderAppController into resources/views/rider.blade.php.
 */

declare global {
  interface Window {
    __RIDER__: RiderBoot
  }
}

captureInstallPrompt()
resetOnboardingOnInstall()

if (wantsSolidGlass()) document.documentElement.dataset.glass = 'solid'

// After a deploy, an open app may try to load a chunk that no longer exists.
// Reload once to pick up the new build (same guard as the portal's app.tsx).
const CHUNK_RELOAD_KEY = 'rider_chunk_reload_at'
window.addEventListener('vite:preloadError', (event) => {
  try {
    const last = Number(sessionStorage.getItem(CHUNK_RELOAD_KEY) || 0)
    if (Date.now() - last > 10_000) {
      event.preventDefault()
      sessionStorage.setItem(CHUNK_RELOAD_KEY, String(Date.now()))
      window.location.reload()
    }
  } catch {
    // Storage blocked: let the error surface rather than risk a reload loop.
  }
})

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/rider-sw.js', { scope: '/rider/' }).catch(() => {
      // The app works without it; it only speeds up repeat opens.
    })
  })
}

createRoot(document.getElementById('rider-app')!).render(
  <StrictMode>
    <RiderApp boot={window.__RIDER__} />
  </StrictMode>
)
