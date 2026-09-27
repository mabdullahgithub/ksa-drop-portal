import { useEffect } from 'react'

/** A colour token from resources/css/rider.css, e.g. cssColor('--brand'). */
export function cssColor(name: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

/**
 * Paint the phone's status bar (Android, and the browser bar elsewhere) to
 * match a full-colour screen while it's up. null leaves it as it is.
 */
export function useStatusBarColor(color: string | null) {
  useEffect(() => {
    if (!color) return
    const metas = Array.from(document.querySelectorAll<HTMLMetaElement>('meta[name="theme-color"]'))
    const before = metas.map((meta) => meta.content)
    metas.forEach((meta) => (meta.content = color))
    return () => metas.forEach((meta, i) => (meta.content = before[i]))
  }, [color])
}
