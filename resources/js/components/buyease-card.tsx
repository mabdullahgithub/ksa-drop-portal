import { useState } from 'react'
import { toast } from 'sonner'
import {
  Sparkles,
  Info,
  Globe,
  ExternalLink,
  ShieldCheck,
  Banknote,
  PackageCheck,
  BadgePercent,
  Copy,
  Check,
  Newspaper,
} from 'lucide-react'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { IconBuyease, IconShopify } from '@/assets/brand-icons'

const BUYEASE_URL = 'https://www.buyease.app'
const BUYEASE_BLOG_URL = 'https://www.buyease.app/blog'
const PROMO_CODE = 'KSADROP2026'

interface BuyEaseCardProps {
  name: string
  description: string | null
}

export function BuyEaseCard({ name, description }: BuyEaseCardProps) {
  const [copied, setCopied] = useState(false)

  const copyPromoCode = async () => {
    try {
      await navigator.clipboard.writeText(PROMO_CODE)
      setCopied(true)
      toast.success(`Code ${PROMO_CODE} copied — apply it at BuyEase checkout for your exclusive discount.`)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      // Clipboard unavailable (e.g. non-secure context) — code stays visible to copy manually.
    }
  }

  return (
    <li className='group relative overflow-hidden rounded-lg border border-teal-200/80 bg-gradient-to-br from-teal-50/70 via-background to-background p-4 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-lg hover:shadow-teal-100/50 dark:border-teal-900/70 dark:from-teal-950/30 dark:hover:border-teal-700 dark:hover:shadow-teal-950/30'>
      {/* Soft brand glow */}
      <div
        aria-hidden
        className='pointer-events-none absolute -top-14 -right-14 size-36 rounded-full bg-teal-400/15 blur-2xl transition-transform duration-500 group-hover:scale-125 dark:bg-teal-500/10'
      />

      <div className='relative mb-4 flex items-start justify-between'>
        <div className='flex size-16 items-center justify-center overflow-hidden rounded-xl shadow-md ring-1 ring-teal-900/10 dark:ring-white/10'>
          <IconBuyease />
        </div>
        <div className='flex items-center gap-1.5'>
          <span className='inline-flex items-center gap-1 rounded-md border border-teal-200 bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-400'>
            <Sparkles className='h-3 w-3' />
            New
          </span>
          <Popover>
            <PopoverTrigger
              aria-label='About BuyEase'
              className='text-muted-foreground inline-flex size-6 cursor-pointer items-center justify-center rounded-full transition-colors outline-none hover:bg-teal-100 hover:text-teal-700 dark:hover:bg-teal-900 dark:hover:text-teal-400'
            >
              <Info className='h-4 w-4' />
            </PopoverTrigger>
            <PopoverContent align='end' className='w-80'>
              <div className='space-y-3'>
                <div className='flex items-center gap-2.5'>
                  <div className='size-9 shrink-0 overflow-hidden rounded-lg'>
                    <IconBuyease />
                  </div>
                  <div>
                    <p className='text-sm font-semibold'>BuyEase COD Form &amp; Upsells</p>
                    <p className='text-muted-foreground text-xs'>
                      AI-Powered Commerce Tools
                    </p>
                  </div>
                </div>
                <p className='text-muted-foreground text-xs leading-relaxed'>
                  AI-powered commerce tools helping Shopify merchants sell
                  smarter, not harder — with COD order forms, one-click upsells,
                  and built-in fraud prevention.
                </p>
                <ul className='space-y-1.5 text-xs'>
                  <li className='flex items-center gap-2'>
                    <Banknote className='h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400' />
                    High-converting COD order forms
                  </li>
                  <li className='flex items-center gap-2'>
                    <PackageCheck className='h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400' />
                    Upsells &amp; order bumps that lift revenue
                  </li>
                  <li className='flex items-center gap-2'>
                    <ShieldCheck className='h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400' />
                    Fraud detection &amp; risky-order screening
                  </li>
                </ul>
                <div className='flex items-center gap-3'>
                  <a
                    href={BUYEASE_URL}
                    target='_blank'
                    rel='noopener noreferrer'
                    className='inline-flex items-center gap-1 text-xs font-medium text-teal-700 hover:underline dark:text-teal-400'
                  >
                    www.buyease.app
                    <ExternalLink className='h-3 w-3' />
                  </a>
                  <a
                    href={BUYEASE_BLOG_URL}
                    target='_blank'
                    rel='noopener noreferrer'
                    className='inline-flex items-center gap-1 text-xs font-medium text-teal-700 hover:underline dark:text-teal-400'
                  >
                    Blog
                    <ExternalLink className='h-3 w-3' />
                  </a>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </div>

      <div className='relative'>
        <h2 className='mb-1 font-semibold'>{name}</h2>
        <p className='line-clamp-2 text-sm text-gray-500 dark:text-gray-400'>{description}</p>
      </div>

      {/* Exclusive discount for KSA Drop clients */}
      <button
        type='button'
        onClick={copyPromoCode}
        title='Click to copy the discount code'
        className='group/promo relative mt-3 flex w-full cursor-pointer items-center justify-between gap-2 rounded-md border border-dashed border-teal-300 bg-teal-50/60 px-3 py-2 text-left transition-colors hover:border-teal-400 hover:bg-teal-50 dark:border-teal-700 dark:bg-teal-950/50 dark:hover:border-teal-500 dark:hover:bg-teal-950'
      >
        <span className='flex items-center gap-2'>
          <BadgePercent className='h-4 w-4 shrink-0 text-teal-600 dark:text-teal-400' />
          <span className='flex flex-col'>
            <span className='text-[11px] font-medium text-teal-800 dark:text-teal-300'>
              Exclusive discount for KSA Drop clients
            </span>
            <span className='font-mono text-sm font-bold tracking-wide text-teal-700 dark:text-teal-400'>
              {PROMO_CODE}
            </span>
          </span>
        </span>
        {copied ? (
          <span className='inline-flex shrink-0 items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400'>
            <Check className='h-3.5 w-3.5' />
            Copied!
          </span>
        ) : (
          <span className='inline-flex shrink-0 items-center gap-1 rounded-md border border-teal-300 bg-white px-2 py-1 text-[11px] font-medium text-teal-700 shadow-sm transition-colors group-hover/promo:bg-teal-600 group-hover/promo:text-white dark:border-teal-700 dark:bg-teal-900 dark:text-teal-300 dark:group-hover/promo:bg-teal-600 dark:group-hover/promo:text-white'>
            <Copy className='h-3.5 w-3.5' />
            Copy code
          </span>
        )}
      </button>

      <div className='bg-border relative my-3 h-px' />

      <div className='relative flex flex-wrap items-center justify-between gap-x-2 gap-y-2'>
        <div className='flex items-center gap-1.5'>
          <a
            href={BUYEASE_URL}
            target='_blank'
            rel='noopener noreferrer'
            className='inline-flex items-center gap-1.5 rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1.5 text-xs font-medium text-teal-700 transition-colors hover:bg-teal-100 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-400 dark:hover:bg-teal-900'
          >
            <Globe className='h-3.5 w-3.5' />
            buyease.app
            <ExternalLink className='h-3 w-3 opacity-60' />
          </a>
          <a
            href={BUYEASE_BLOG_URL}
            target='_blank'
            rel='noopener noreferrer'
            className='inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground'
          >
            <Newspaper className='h-3.5 w-3.5' />
            Blog
          </a>
        </div>
        <span
          title='BuyEase will be available on the Shopify App Store soon'
          className='text-muted-foreground inline-flex items-center gap-1.5 text-xs'
        >
          <span className='size-4 shrink-0'>
            <IconShopify tileClassName='bg-transparent p-0 dark:bg-transparent' />
          </span>
          App Store
          <span className='rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400'>
            Soon
          </span>
        </span>
      </div>
    </li>
  )
}
