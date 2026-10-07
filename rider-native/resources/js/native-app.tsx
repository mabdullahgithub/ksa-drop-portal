import '../css/native.css'

import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RiderApp } from '@/features/rider-app/app'
import { setHost } from '@/features/rider-app/lib/host'
import type { RiderBoot } from '@/features/rider-app/types'
import { forgetToken, readToken, saveToken, start } from './session'

/**
 * KSA Express rider app for phones — the portal's rider screens, inside a
 * NativePHP web view. Served by routes/web.php into
 * resources/views/app.blade.php.
 */

type NativeBoot = {
  /** The portal's address: every /rider/api/* call goes there. */
  apiBase: string
  /** Where NativePHP serves public/ from. */
  assetBase: string
  /** The token of a sign-in link the phone opened the app with. */
  activation: string | null
}

declare global {
  interface Window {
    __RIDER_NATIVE__: NativeBoot
    __RIDER__: RiderBoot
  }
}

const { apiBase, assetBase, activation } = window.__RIDER_NATIVE__

// Back to the start: the app works out afresh who is signed in.
const restart = () => window.location.replace('/')

setHost({
  native: true,
  apiBase,
  assetBase,
  token: readToken,
  signedIn: ({ token }) => {
    if (token) saveToken(token)
    restart()
  },
  signedOut: () => {
    forgetToken()
    restart()
  },
})

// Signed out by the office, or signed in on another phone, while the app was
// open. The screens switch to sign-in themselves (app.tsx).
window.addEventListener('rider:signed-out', (event) => {
  if ((event as CustomEvent<string>).detail !== 'suspended') forgetToken()
})

start(activation).then((boot) => {
  // No `build`: a phone app is updated by the store, not by reloading.
  window.__RIDER__ = boot

  createRoot(document.getElementById('rider-app')!).render(
    <StrictMode>
      <RiderApp boot={boot} />
    </StrictMode>
  )
})
