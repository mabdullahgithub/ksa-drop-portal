import { type SVGAttributes } from 'react'
import { cn } from '@/lib/utils'

// KSA Express is our own in-house courier — a simple monogram for the Apps grid.
export function IconKsaExpress({ className, ...props }: SVGAttributes<SVGSVGElement>) {
  return (
    <svg
      viewBox='0 0 64 64'
      className={cn('w-full h-full', className)}
      role='img'
      aria-label='KSA Express'
      {...props}
    >
      <rect width='64' height='64' rx='12' fill='#111827' />
      <text
        x='32'
        y='40'
        textAnchor='middle'
        fontFamily='system-ui, sans-serif'
        fontSize='22'
        fontWeight='700'
        fill='#ffffff'
      >
        KSX
      </text>
    </svg>
  )
}
