/**
 * Where the rider app is running.
 *
 * The same screens are served two ways: by the portal as an installable web
 * app (same origin, signed in by an HttpOnly cookie), and inside the phone
 * app in rider-native/ (its own origin, signed in by a token it keeps and
 * sends as a Bearer header). The web app is the default; the phone app's
 * entry calls setHost() before anything renders.
 */

export type Host = {
  /** Inside the phone app rather than a browser. */
  native: boolean
  /** Put in front of /rider/api/*: '' on the portal, the portal's address in the phone app. */
  apiBase: string
  /** Put in front of files from public/, e.g. /rider-icons/*. */
  assetBase: string
  /** The device token to send, in the phone app. The web app's is a cookie scripts can't read. */
  token: () => string | null
  /** After the activation link or phone + PIN let this phone in. */
  signedIn: (answer: SignInAnswer) => void | Promise<void>
  /** After the rider logged out from Profile. */
  signedOut: (answer: { redirect: string }) => void | Promise<void>
  /**
   * Open the phone's camera and hand back the photo, or null if the rider
   * backed out. Only the phone app has one: a web view can't open a file
   * input, so the browser's <input type='file'> is used when this is missing.
   */
  takePhoto?: () => Promise<Blob | null>
}

/** The web app is sent on to `redirect`; the phone app is handed its `token`. */
export type SignInAnswer = { redirect?: string; token?: string }

const web: Host = {
  native: false,
  apiBase: '',
  assetBase: '',
  token: () => null,
  signedIn: ({ redirect }) => window.location.replace(redirect ?? '/rider/app'),
  signedOut: ({ redirect }) => window.location.replace(redirect),
}

let current: Host = web

export function setHost(host: Partial<Host>) {
  current = { ...current, ...host }
}

export function host(): Host {
  return current
}

/** A file from public/, wherever this host serves it from. */
export function assetUrl(path: string): string {
  return current.assetBase + path
}
