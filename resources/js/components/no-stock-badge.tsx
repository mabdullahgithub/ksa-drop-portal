import { PackageX } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import type { OutOfStockItem } from '@/types/order'

const badgeClass =
  'inline-flex items-center gap-1 rounded-full border border-red-300 bg-red-100 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-red-700 dark:border-red-500/40 dark:bg-red-900/30 dark:text-red-400'

/**
 * The red mark on an order waiting on a product the client has none left of.
 * Renders nothing for an order that is fine.
 */
export function NoStockBadge({ items, className }: { items?: OutOfStockItem[] | null; className?: string }) {
  if (!items || items.length === 0) return null

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className={cn(badgeClass, className)}>
          <PackageX className='h-3 w-3' />
          No stock
        </span>
      </TooltipTrigger>
      <TooltipContent side='right' className='max-w-64 text-left'>
        <div className='font-semibold'>Out of stock — do not process</div>
        <ul className='mt-1 space-y-0.5'>
          {items.map((item) => (
            <li key={item.item_id}>
              {item.name}
              {item.sku ? ` (${item.sku})` : ''}
            </li>
          ))}
        </ul>
      </TooltipContent>
    </Tooltip>
  )
}

/**
 * The same warning spelled out, for the top of an order's item list.
 */
export function NoStockNotice({ items, staff }: { items?: OutOfStockItem[] | null; staff?: boolean }) {
  if (!items || items.length === 0) return null

  return (
    <div
      role='alert'
      className='mb-3 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-900/20 dark:text-red-300'
    >
      <PackageX className='mt-0.5 h-4 w-4 shrink-0' />
      <div>
        <div className='font-semibold'>
          {items.length === 1 ? 'An item in this order is out of stock' : `${items.length} items in this order are out of stock`}
        </div>
        <div className='mt-0.5'>
          {staff
            ? 'The client has no inventory for it. Do not process this order until stock arrives.'
            : 'This order waits until you send more stock.'}
        </div>
      </div>
    </div>
  )
}

/** Small inline tag for the item itself. */
export function NoStockTag() {
  return (
    <span className={badgeClass}>
      <PackageX className='h-3 w-3' />
      No stock
    </span>
  )
}
