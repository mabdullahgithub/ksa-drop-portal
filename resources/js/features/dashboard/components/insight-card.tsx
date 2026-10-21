import { useEffect, useRef, useState } from 'react'
import { animate, useReducedMotion } from 'framer-motion'
import { cn } from '@/lib/utils'
import { Skeleton } from '@/components/ui/skeleton'

/**
 * The frame every dashboard chart sits in: a quiet card with a hairline of
 * the chart's accent along the top and a faint wash of it in one corner, so
 * the three charts read as a set without competing with their data.
 */
export function InsightCard({
  eyebrow,
  title,
  description,
  icon,
  accent,
  aside,
  className,
  children,
}: {
  eyebrow: string
  title: string
  description: string
  icon: React.ReactNode
  /** A CSS colour, usually a `var(--viz-…)` token. */
  accent: string
  aside?: React.ReactNode
  className?: string
  children: React.ReactNode
}) {
  return (
    <section
      className={cn(
        'relative isolate flex flex-col overflow-hidden rounded-2xl border bg-card text-card-foreground',
        'shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_32px_-16px_rgba(15,23,42,0.18)] dark:shadow-none',
        className
      )}
    >
      <div
        aria-hidden
        className='pointer-events-none absolute inset-x-6 top-0 h-px opacity-80'
        style={{ background: `linear-gradient(90deg, transparent, ${accent}, transparent)` }}
      />
      <div
        aria-hidden
        className='pointer-events-none absolute -top-28 -right-20 -z-10 size-64 rounded-full opacity-[0.09] blur-3xl dark:opacity-[0.14]'
        style={{ background: accent }}
      />

      <header className='flex items-start justify-between gap-4 px-5 pt-5 sm:px-6'>
        <div className='flex min-w-0 items-start gap-3'>
          <span
            className='mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg border bg-background/60 [&_svg]:size-4'
            style={{ color: accent }}
          >
            {icon}
          </span>
          <div className='min-w-0'>
            <p className='text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase'>{eyebrow}</p>
            <h3 className='text-base leading-tight font-semibold tracking-tight'>{title}</h3>
            <p className='mt-0.5 text-xs text-muted-foreground'>{description}</p>
          </div>
        </div>
        {aside}
      </header>

      {/* Fills the card, so a chart beside a taller one can pin content to the bottom. */}
      <div className='flex flex-1 flex-col px-5 pt-4 pb-5 sm:px-6'>{children}</div>
    </section>
  )
}

/** Rows of placeholders shaped like a ranked list. */
export function InsightListSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className='space-y-3'>
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className='flex items-center gap-3'>
          <Skeleton className='size-9 rounded-xl' />
          <div className='flex-1 space-y-1.5'>
            <Skeleton className='h-3 w-1/3' />
            <Skeleton className='h-2 w-full' />
          </div>
          <Skeleton className='h-6 w-14' />
        </div>
      ))}
    </div>
  )
}

export function InsightEmpty({ children }: { children: React.ReactNode }) {
  return (
    <div className='grid min-h-40 place-items-center rounded-xl border border-dashed text-center text-xs text-muted-foreground'>
      <p className='max-w-56'>{children}</p>
    </div>
  )
}

export function formatSar(value: number) {
  if (value >= 1_000_000) return `SAR ${(value / 1_000_000).toFixed(1)}M`
  if (value >= 10_000) return `SAR ${(value / 1000).toFixed(0)}K`
  if (value >= 1000) return `SAR ${(value / 1000).toFixed(1)}K`
  return `SAR ${Math.round(value)}`
}

export function percent(part: number, whole: number) {
  if (whole <= 0) return '0%'
  const value = (part / whole) * 100
  return `${value < 10 && value > 0 ? value.toFixed(1) : Math.round(value)}%`
}

/**
 * A number that counts up to its value — from wherever it last was, so a new
 * period glides between figures instead of restarting at zero.
 */
export function CountUp({
  value,
  format = (v) => Math.round(v).toLocaleString(),
  duration = 1.1,
}: {
  value: number
  format?: (value: number) => string
  duration?: number
}) {
  const reduce = useReducedMotion()
  const from = useRef(0)
  const [shown, setShown] = useState(reduce ? value : 0)

  useEffect(() => {
    if (reduce) {
      setShown(value)
      return
    }
    const controls = animate(from.current, value, {
      duration,
      ease: [0.16, 1, 0.3, 1],
      onUpdate: (v) => {
        from.current = v
        setShown(v)
      },
    })
    return () => controls.stop()
  }, [value, duration, reduce])

  return <>{format(shown)}</>
}
