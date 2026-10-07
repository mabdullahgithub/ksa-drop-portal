import { useEffect, useState } from 'react'
import { cn } from '@/lib/utils'
import { host } from '../lib/host'

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}

/**
 * The photo is the rider's own, behind sign-in. On the portal the cookie
 * goes with an <img> by itself; the phone app has to fetch it with its token
 * and show the copy.
 */
function useSignedInImage(url: string | null): string | null {
  const { native, token } = host()
  const [copy, setCopy] = useState<string | null>(null)

  useEffect(() => {
    if (!native || !url) return

    let objectUrl: string | null = null
    let gone = false

    fetch(url, { headers: { 'X-Rider-Client': 'native', Authorization: `Bearer ${token() ?? ''}` } })
      .then((response) => (response.ok ? response.blob() : Promise.reject()))
      .then((blob) => {
        if (gone) return
        objectUrl = URL.createObjectURL(blob)
        setCopy(objectUrl)
      })
      .catch(() => {
        // No signal, or no photo any more: the initials show.
      })

    return () => {
      gone = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
      setCopy(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [native, url])

  return native ? copy : url
}

/** The rider's photo, or their initials when there isn't one. */
export function RiderPhoto({ name, photoUrl, className }: { name: string; photoUrl: string | null; className?: string }) {
  const [broken, setBroken] = useState(false)
  const src = useSignedInImage(photoUrl)

  return src && !broken ? (
    <img src={src} alt='' onError={() => setBroken(true)} className={cn('shrink-0 rounded-full bg-muted object-cover', className)} />
  ) : (
    <span className={cn('flex shrink-0 items-center justify-center rounded-full bg-white font-bold text-brand', className)}>
      {initials(name) || '?'}
    </span>
  )
}
