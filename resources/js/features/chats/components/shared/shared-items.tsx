import { Link, usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import { ArrowDownLeft, ArrowUpRight, Copy, ExternalLink, Lock, Package, ShoppingCart, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { usePermissions } from '@/hooks/use-permissions'
import type { PageProps } from '@/types'
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

export function ItemThumb({ item }: { item: ChatSharedItem }) {
  const Icon = item.type === 'order' ? ShoppingCart : Package
  return (
    <span className='flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted'>
      {item.type === 'product' && item.image_url ? (
        <img src={item.image_url} alt='' loading='lazy' className='size-full object-cover' />
      ) : (
        <Icon className='size-4 opacity-80' />
      )}
    </span>
  )
}

/** What gets copied: the order number, or the product's code. */
const itemId = (item: ChatSharedItem) => (item.type === 'order' ? item.title : item.code || item.sku || String(item.id))

async function copy(value: string) {
  try {
    await navigator.clipboard.writeText(value)
    toast.success(`${value} copied`)
  } catch {
    toast.error('Could not copy. Select it and copy it yourself.')
  }
}

/**
 * Who sent a group of cards, told by colour before any name is read: the
 * reader's own side in the brand orange (as their bubbles are), the other
 * side in blue, an internal note in amber. The arrow says which way it went.
 */
const TONES = {
  sent: { frame: 'border-[#f26722]/60', head: 'bg-[#f26722] text-white', icon: ArrowUpRight },
  received: { frame: 'border-sky-600/60', head: 'bg-sky-600 text-white', icon: ArrowDownLeft },
  note: { frame: 'border-dashed border-amber-500/70', head: 'bg-amber-500 text-white', icon: Lock },
} as const

export type CardTone = keyof typeof TONES

/**
 * The orders and products attached to a message, as they were when it was
 * sent, each with its ID to copy and a link to open it: on the reader's own
 * pages, and only where they may go.
 */
export function SharedItemCards({
  items,
  side,
  clientId,
  tone,
  sender,
}: {
  items: ChatSharedItem[]
  side: ChatSide
  /** Whose chat this is: where the team opens a product. */
  clientId?: number
  tone: CardTone
  /** "You", or who sent it. */
  sender: string
}) {
  const { can } = usePermissions()
  const { auth } = usePage<PageProps>().props
  const { frame, head, icon: Direction } = TONES[tone]
  const what =
    items.length > 1 ? `${items.length} items` : items[0]?.type === 'order' ? 'an order' : 'a product'

  const linkFor = (item: ChatSharedItem): string | null => {
    if (item.type === 'order') {
      const query = `?order=${encodeURIComponent(item.title)}`
      if (side === 'client') return `/portal/orders${query}`
      return can('view orders') ? `/orders${query}` : null
    }

    if (!item.code) return null
    if (side === 'client') {
      return auth.client?.is_fulfilment && auth.portal_features?.includes('inventory')
        ? `/portal/inventory?product=${encodeURIComponent(item.code)}`
        : null
    }
    return clientId && can('view client details') && can('view client products')
      ? `/client/${clientId}?tab=inventory&product=${encodeURIComponent(item.code)}`
      : null
  }

  return (
    <div className={cn('w-72 max-w-full overflow-hidden rounded-lg border-2 bg-card text-start text-card-foreground shadow-xs', frame)}>
      <div className={cn('flex items-center gap-1.5 px-2.5 py-1.5 text-[11px]', head)}>
        <Direction className='size-3.5 shrink-0' />
        <span className='min-w-0 flex-1 truncate'>
          <span className='font-semibold'>{sender}</span> shared {what}
        </span>
      </div>

      <div className='divide-y'>
        {items.map((item) => {
          const href = linkFor(item)
          const id = itemId(item)

          return (
            <div key={`${item.type}-${item.id}`}>
              <div className='flex items-center gap-2.5 p-2.5'>
                <ItemThumb item={item} />
                <div className='min-w-0 flex-1'>
                  <div className='text-[10px] font-medium tracking-wide text-muted-foreground uppercase'>
                    {item.type === 'order' ? 'Order' : 'Product'}
                  </div>
                  <div className='truncate text-sm leading-snug font-semibold' dir='auto'>{item.title}</div>
                  <div className='truncate text-[11px] text-muted-foreground'>
                    {item.type === 'product' && item.code && <span className='font-mono'>{item.code} · </span>}
                    {itemSummary(item)}
                  </div>
                </div>
              </div>
              <div className={cn('grid divide-x border-t text-[11px] font-medium', href ? 'grid-cols-2' : 'grid-cols-1')}>
                <button
                  type='button'
                  onClick={() => void copy(id)}
                  className='flex items-center justify-center gap-1.5 py-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground'
                >
                  <Copy className='size-3' /> Copy ID
                </button>
                {href && (
                  <Link
                    href={href}
                    className='flex items-center justify-center gap-1.5 py-1.5 text-[#f26722] transition-colors hover:bg-[#f26722]/10'
                  >
                    <ExternalLink className='size-3' /> {item.type === 'order' ? 'Open order' : 'Open product'}
                  </Link>
                )}
              </div>
            </div>
          )
        })}
      </div>
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
