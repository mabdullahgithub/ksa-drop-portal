import { Link } from '@inertiajs/react'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'

/**
 * Solid-fill stat card with an overhanging bubble badge.
 *
 * Lifted out of the WhatsApp inbox so the dashboard shows the same KPI row.
 * Both fill properties were computed, not eyeballed:
 *  - White text clears WCAG AA (4.5:1) on every fill — the lighter -600 steps
 *    of orange and emerald failed at 3.6/3.8:1 and were dropped to -700.
 *  - As a set the fills pass CVD separation (worst ΔE 12.0) and the
 *    normal-vision floor (25.4), so the cards stay tellable apart.
 * Each card also carries an icon and a label, so colour is never the only cue.
 */
const TONES = {
  teal: { fill: 'bg-teal-700', badge: 'bg-teal-900' },
  orange: { fill: 'bg-orange-700', badge: 'bg-orange-900' },
  blue: { fill: 'bg-blue-600', badge: 'bg-blue-800' },
  emerald: { fill: 'bg-emerald-700', badge: 'bg-emerald-900' },
  violet: { fill: 'bg-violet-600', badge: 'bg-violet-800' },
  rose: { fill: 'bg-rose-600', badge: 'bg-rose-800' },
} as const

export type StatCardTone = keyof typeof TONES

/**
 * Background textures, one per dashboard band, so a card is placeable at a
 * glance even when its number is scrolled away from its heading.
 *
 * Every pattern is drawn in black, never white: the tones were picked with
 * only a little headroom over the 4.5:1 floor for white text, and a white
 * overlay would spend it. Black at 12-16% moves contrast the safe way.
 * Each is masked to fade out towards the text, so the pattern reads as
 * material rather than as noise behind the value.
 */
const TEXTURES = {
  /** The original: soft bokeh circles. WhatsApp's signature, shared with the inbox. */
  blobs: null,
  dots: 'radial-gradient(rgba(0,0,0,0.28) 1px, transparent 1px)',
  grid: 'linear-gradient(rgba(0,0,0,0.2) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.2) 1px, transparent 1px)',
} as const

const TEXTURE_SIZES: Record<keyof typeof TEXTURES, string | undefined> = {
  blobs: undefined,
  dots: '9px 9px',
  grid: '14px 14px',
}

export type StatCardTexture = keyof typeof TEXTURES

/** 1,284 / 12.9K — keeps the value on one line at any volume. */
export function compactNumber(value: number) {
  if (value < 1000) return String(value)
  if (value < 1_000_000) return `${(value / 1000).toFixed(value < 10_000 ? 1 : 0)}K`
  return `${(value / 1_000_000).toFixed(1)}M`
}

type StatCardProps = {
  icon: React.ReactNode
  label: string
  /** Numbers are compacted; pass a string to format it yourself. */
  value: number | string
  /** Small unit ahead of the value, e.g. a currency code. */
  pre?: string
  /** Small trailing qualifier, e.g. `/ 1.2K`. */
  sub?: string
  tone: StatCardTone
  /** Distinguishes one dashboard band from the next. Defaults to `blobs`. */
  texture?: StatCardTexture
  hint?: string
  /** Turns the whole card into a link to the page the number came from. */
  href?: string
}

export function StatCard({
  icon,
  label,
  value,
  pre,
  sub,
  tone,
  texture = 'blobs',
  hint,
  href,
}: StatCardProps) {
  const { fill, badge } = TONES[tone]
  const pattern = TEXTURES[texture]

  const surface = (
    <div
      className={cn(
        'relative overflow-hidden rounded-xl px-3 pb-2.5 pt-3.5 transition-shadow',
        fill,
        href && 'group-hover:shadow-lg'
      )}
    >
      {/* Decoration — purely atmospheric, hidden from assistive tech. */}
      {pattern ? (
        <span
          aria-hidden
          className='pointer-events-none absolute inset-0'
          style={{
            backgroundImage: pattern,
            backgroundSize: TEXTURE_SIZES[texture],
            // Densest at the bottom-start corner, gone by the time it reaches
            // the value and the badge.
            maskImage: 'linear-gradient(to top right, black, transparent 70%)',
            WebkitMaskImage: 'linear-gradient(to top right, black, transparent 70%)',
          }}
        />
      ) : (
        <>
          <span aria-hidden className='pointer-events-none absolute -bottom-5 -start-4 h-14 w-14 rounded-full bg-black/10' />
          <span aria-hidden className='pointer-events-none absolute bottom-3.5 start-8 h-2.5 w-2.5 rounded-full bg-black/10' />
        </>
      )}
      {/* Kept across every texture so the family still reads as one set. */}
      <span aria-hidden className='pointer-events-none absolute -top-6 start-4 h-12 w-12 rounded-full bg-white/10' />

      <div className='relative'>
        {/* Proportional figures: a standalone value, not a column to align. */}
        <div className='text-xl font-bold leading-none tracking-tight text-white'>
          {pre && <span className='me-1 text-[11px] font-medium text-white/70'>{pre}</span>}
          {typeof value === 'number' ? compactNumber(value) : value}
          {sub && <span className='ms-1 text-[11px] font-medium text-white/70'>{sub}</span>}
        </div>
        <p className='mt-1.5 truncate text-[11px] font-medium leading-snug text-white/85'>{label}</p>
      </div>
    </div>
  )

  // Outer wrapper stays unclipped so the badge can overhang; the inner surface
  // clips the decorative blobs.
  const card = (
    <div className={cn('relative pt-2.5 pe-2', href && 'group')}>
      {href ? (
        <Link href={href} aria-label={label}>
          {surface}
        </Link>
      ) : (
        surface
      )}

      {/*
        Bubble badge, overhanging the top-end corner.

        One element, not a circle plus a rotated square: squaring the
        bottom-start corner of a full-round shape gives the bubble its point,
        so the 1px ring traces a single silhouette. The two-element version
        left a visible seam where the ring outlined each piece separately.
        Logical corner (`rounded-es`) so the point still faces the card in RTL.
      */}
      <span className='pointer-events-none absolute end-3 top-0 z-10'>
        <span
          className={cn(
            'grid h-7 w-7 place-items-center rounded-full rounded-es-none text-white shadow-md ring-1 ring-background',
            badge
          )}
        >
          {icon}
        </span>
      </span>
    </div>
  )

  if (!hint) return card

  return (
    <Tooltip>
      <TooltipTrigger asChild>{card}</TooltipTrigger>
      <TooltipContent className='max-w-64'>
        <p className='text-xs'>{hint}</p>
      </TooltipContent>
    </Tooltip>
  )
}

/** Same footprint as a StatCard, so the row does not jump when data lands. */
export function StatCardSkeleton() {
  return (
    <div className='relative pt-2.5 pe-2'>
      <div className='rounded-xl bg-muted px-3 pb-2.5 pt-3.5'>
        <Skeleton className='h-5 w-14 bg-foreground/10' />
        <Skeleton className='mt-2 h-3 w-20 bg-foreground/10' />
      </div>
      <span className='absolute end-3 top-0 z-10'>
        <Skeleton className='h-7 w-7 rounded-full rounded-es-none ring-1 ring-background' />
      </span>
    </div>
  )
}
