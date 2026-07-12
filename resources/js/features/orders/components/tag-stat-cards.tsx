import { useOrderStatistics } from '@/hooks/useOrders'
import { Tag as TagIcon } from 'lucide-react'

function hexToRgba(hex: string | null | undefined, alpha: number) {
  if (!hex || hex.length < 4) return `rgba(128, 128, 128, ${alpha})`
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function TagCardSkeleton() {
  return (
    <div className='flex flex-col justify-between rounded-lg border border-muted/60 bg-card p-3 shadow-sm'>
      <div className='mb-2 h-3 w-16 rounded bg-muted animate-pulse' />
      <div className='h-5 w-8 rounded bg-muted animate-pulse' />
    </div>
  )
}

interface TagStatCardsProps {
  activeTag?: string | null
  onTagClick?: (tagName: string) => void
}

export function TagStatCards({ activeTag, onTagClick }: TagStatCardsProps) {
  const { statistics, loading } = useOrderStatistics()

  const tags = statistics?.by_tag ?? []

  if (!loading && tags.length === 0) return null

  return (
    <div>
      <div className='flex items-center gap-1.5 mb-3'>
        <TagIcon className='h-3.5 w-3.5 text-muted-foreground' />
        <h3 className='text-sm font-semibold'>Tags</h3>
      </div>

      {loading ? (
        <div className='grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'>
          {[...Array(6)].map((_, i) => (
            <TagCardSkeleton key={i} />
          ))}
        </div>
      ) : (
        <div className='grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'>
          {tags.map((tag) => {
            const isActive = activeTag === tag.name
            return (
              <button
                key={tag.id}
                onClick={() => onTagClick?.(tag.name)}
                className='flex flex-col justify-between rounded-lg border p-3 shadow-sm hover:shadow-md transition-all cursor-pointer text-left'
                style={{
                  backgroundColor: hexToRgba(tag.color, isActive ? 0.14 : 0.06),
                  borderColor: isActive ? tag.color : hexToRgba(tag.color, 0.3),
                }}
              >
                <div className='flex items-center justify-between gap-1 mb-2'>
                  <p className='truncate text-[10px] font-medium uppercase tracking-wide text-muted-foreground leading-none'>
                    {tag.name}
                  </p>
                  <span className='shrink-0 inline-flex h-2.5 w-2.5 rounded-full' style={{ backgroundColor: tag.color }} />
                </div>
                <p className='text-sm font-bold tabular-nums' style={{ color: tag.color }}>
                  {tag.count.toLocaleString()}
                </p>
              </button>
            )
          })}
        </div>
      )}
    </div>
  )
}
