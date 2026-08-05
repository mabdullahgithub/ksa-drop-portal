import { type SVGAttributes } from 'react'
import { cn } from '@/lib/utils'

// No official iMile logo asset is bundled with this project — this is a
// generic monogram placeholder (not iMile's trademark) so the Apps grid has
// a visual for the connector. Swap for a real logo asset if iMile provides one.
export function IconImile({ className, ...props }: SVGAttributes<SVGSVGElement>) {
  return (
    <svg
      viewBox='0 0 64 64'
      className={cn('w-full h-full', className)}
      role='img'
      aria-label='iMile'
      {...props}
    >
      <rect width='64' height='64' rx='12' fill='#FF6A00' />
      <text
        x='32'
        y='40'
        textAnchor='middle'
        fontFamily='system-ui, sans-serif'
        fontSize='24'
        fontWeight='700'
        fill='#ffffff'
      >
        iM
      </text>
    </svg>
  )
}
