import { type SVGProps } from 'react'
import { cn } from '@/lib/utils'

export function IconJnt({ className, ...props }: SVGProps<SVGSVGElement>) {
  return (
    <svg
      role='img'
      viewBox='0 0 24 24'
      xmlns='http://www.w3.org/2000/svg'
      width='24'
      height='24'
      className={cn('[&>path]:fill-current', className)}
      fill='currentColor'
      {...props}
    >
      <title>J&T Express</title>
      <path d='M2 6h3v8c0 1.1-.9 2-2 2H2c-1.1 0-2-.9-2-2v-1h2v1h1V6zm5 4h2v2h1v-2h2v6h-2v-2H9v2H7v-6zm9-4h6v2h-2v8h-2V8h-2V6z' />
    </svg>
  )
}
