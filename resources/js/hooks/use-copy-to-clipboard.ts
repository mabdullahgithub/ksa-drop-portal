import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * Copy text to the clipboard and expose a short-lived `copied` flag so callers
 * can swap a copy icon for a checkmark.
 *
 * Falls back to a hidden textarea + execCommand: the async Clipboard API is
 * unavailable on non-HTTPS origins and inside the sandboxed Shopify admin iframe.
 */
export function useCopyToClipboard(resetDelay = 2000) {
  const [copied, setCopied] = useState(false)
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    return () => {
      if (timer.current) clearTimeout(timer.current)
    }
  }, [])

  const copy = useCallback(
    async (text: string) => {
      const flash = () => {
        setCopied(true)
        if (timer.current) clearTimeout(timer.current)
        timer.current = setTimeout(() => setCopied(false), resetDelay)
      }

      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text)
          flash()
          return true
        }

        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.style.position = 'fixed'
        textarea.style.opacity = '0'
        document.body.appendChild(textarea)
        textarea.select()
        const ok = document.execCommand('copy')
        document.body.removeChild(textarea)

        if (ok) flash()
        return ok
      } catch {
        return false
      }
    },
    [resetDelay]
  )

  return { copied, copy }
}
