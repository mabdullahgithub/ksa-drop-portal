import { type ImgHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

export function IconJnt({ className, ...props }: ImgHTMLAttributes<HTMLImageElement>) {
  return (
    <img
      src='/images/integrations/j&t-logo.webp'
      alt='J&T Express'
      className={cn('w-full h-full object-cover', className)}
      {...props}
    />
  )
}
