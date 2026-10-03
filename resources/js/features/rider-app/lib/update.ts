/**
 * An installed app stays open for days, still running the build it started
 * with — a new feature wouldn't show until the phone closed the app. The
 * server says which build is live (/rider/api/me); when it isn't this one,
 * reload into it.
 */

const RELOADED_FOR_KEY = 'rider_reloaded_for'

/** Call only when nothing is open: the reload throws away whatever is on screen. */
export function reloadForNewBuild(live: string | null | undefined) {
  const running = window.__RIDER__.build
  if (!live || !running || live === running) return

  try {
    // Once per build: a phone that can't fetch the new one must not keep reloading.
    if (sessionStorage.getItem(RELOADED_FOR_KEY) === live) return
    sessionStorage.setItem(RELOADED_FOR_KEY, live)
  } catch {
    // Storage blocked: stay on this build rather than risk a reload loop.
    return
  }

  window.location.reload()
}
