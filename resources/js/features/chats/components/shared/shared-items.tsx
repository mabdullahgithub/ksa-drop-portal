import { Link } from '@inertiajs/react'
import { Package, ShoppingCart, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { usePermissions } from '@/hooks/use-permissions'
import type { ChatSharedItem, ChatSide } from '../../types'

const words = (value: string | null | undefined) => (value ? value.replace(/_/g, ' ') : null)

/** The second line of an order's or product's card. */
export function itemSummary(item: ChatSharedItem): string {
  if (item.type === 'order') {
    const status = words(item.status)
    return [
      status && status.charAt(0).toUpperCase() + status.slice(1),
      item.total != null ? `${item.currency ?? ''} ${Number(item.total).toFixed(2)}`.trim() : null,
    ]
      .filter(Boolean)
      .join(' · ')
  }

  return [item.sku || item.code, item.out_of_stock || !item.quantity ? 'Out of stock' : `${item.quantity} in stock`]
    .filter(Boolean)
    .join(' · ')
}

export function ItemThumb({ item, onDark }: { item: ChatSharedItem; onDark?: boolean }) {
  const Icon = item.type === 'order' ? ShoppingCart : Package
  return (
    <span
      className={cn(
        'flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md',
        onDark ? 'bg-white/20' : 'bg-muted'
      )}
    >
      {item.type === 'product' && item.image_url ? (
        <img src={item.image_url} alt='' loading='lazy' className='size-full object-cover' />
      ) : (
        <Icon className='size-4 opacity-80' />
      )}
    </span>
  )
}

/**
 * The orders and products attached to a message, as they were when it was
 * sent. An order opens on the reader's own orders page.
 */
export function SharedItemCards({ items, side, onDark }: { items: ChatSharedItem[]; side: ChatSide; onDark?: boolean }) {
  const { can } = usePermissions()

  return (
    <div className='flex w-60 max-w-full flex-col gap-1.5'>
      {items.map((item) => {
        const href =
          item.type !== 'order'
            ? null
            : side === 'client'
              ? `/portal/orders?order=${encodeURIComponent(item.title)}`
              : can('view orders')
                ? `/orders?order=${encodeURIComponent(item.title)}`
                : null

        const card = (
          <>
            <ItemThumb item={item} onDark={onDark} />
            <span className='min-w-0 flex-1'>
              <span className={cn('block text-[10px] leading-tight', onDark ? 'text-white/80' : 'text-muted-foreground')}>
                {item.type === 'order' ? 'Order' : 'Product'}
              </span>
              <span className='block truncate text-xs leading-snug font-semibold'>{item.title}</span>
              <span className={cn('block truncate text-[11px] leading-snug', onDark ? 'text-white/85' : 'text-muted-foreground')}>
                {itemSummary(item)}
              </span>
            </span>
          </>
        )
        const style = cn(
          'flex items-center gap-2 rounded-lg border p-1.5 text-start',
          onDark ? 'border-white/25 bg-white/10' : 'bg-background text-foreground'
        )

        return href ? (
          <Link key={`${item.type}-${item.id}`} href={href} className={cn(style, onDark ? 'hover:bg-white/20' : 'hover:bg-muted/60')}>
            {card}
          </Link>
        ) : (
          <div key={`${item.type}-${item.id}`} className={style}>
            {card}
          </div>
        )
      })}
    </div>
  )
}

/** An attached item waiting in the composer, with a way to take it off. */
export function ItemChip({ item, onRemove }: { item: ChatSharedItem; onRemove: () => void }) {
  return (
    <div className='relative flex max-w-52 items-center gap-2 rounded-md border bg-card py-1 ps-1 pe-6 text-xs'>
      <ItemThumb item={item} />
      <span className='min-w-0'>
        <span className='block truncate font-medium'>{item.title}</span>
        <span className='block truncate text-muted-foreground'>{itemSummary(item)}</span>
      </span>
      <button type='button' onClick={onRemove} className='absolute end-1 top-1 rounded p-0.5 text-muted-foreground hover:bg-muted' aria-label={`Remove ${item.title}`}>
        <X className='size-3' />
      </button>
    </div>
  )
}
