import { useState } from 'react'
import { cn } from '@/lib/utils'

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}

/** The rider's photo, or their initials when there isn't one. */
export function RiderPhoto({ name, photoUrl, className }: { name: string; photoUrl: string | null; className?: string }) {
  const [broken, setBroken] = useState(false)

  return photoUrl && !broken ? (
    <img src={photoUrl} alt='' onError={() => setBroken(true)} className={cn('shrink-0 rounded-full bg-muted object-cover', className)} />
  ) : (
    <span className={cn('flex shrink-0 items-center justify-center rounded-full bg-white font-bold text-brand', className)}>
      {initials(name) || '?'}
    </span>
  )
}
