import { type ImgHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

export function IconShopify({ className, ...props }: ImgHTMLAttributes<HTMLImageElement>) {
  return (
    <img
      src='/images/integrations/shopify-logo.jpg'
      alt='Shopify'
      className={cn('w-full h-full object-cover', className)}
      {...props}
    />
  )
}
