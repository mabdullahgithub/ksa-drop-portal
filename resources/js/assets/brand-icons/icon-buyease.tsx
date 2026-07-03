import { type ImgHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

export function IconBuyease({ className, ...props }: ImgHTMLAttributes<HTMLImageElement>) {
  return (
    <img
      src='/images/integrations/buyease.png'
      alt='BuyEase'
      className={cn('w-full h-full object-cover', className)}
      {...props}
    />
  )
}
