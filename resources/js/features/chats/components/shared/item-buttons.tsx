import { Package, ShoppingCart } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { ChatItemSources, ChatSharedItem } from '../../types'

/** "Select Orders" and "Select Inventory", each its own labelled button, shown only where that kind can be attached. */
export function ItemButtons({
  sources,
  onPick,
  className,
}: {
  sources: ChatItemSources
  onPick: (kind: ChatSharedItem['type']) => void
  className?: string
}) {
  const buttons = [
    { kind: 'order' as const, label: 'Select Orders', icon: ShoppingCart, show: !!sources.orders },
    { kind: 'product' as const, label: 'Select Inventory', icon: Package, show: !!sources.products },
  ].filter((button) => button.show)

  if (buttons.length === 0) return null

  return (
    <div className={cn('flex items-center gap-1.5', className)}>
      {buttons.map(({ kind, label, icon: Icon }) => (
        <button
          key={kind}
          type='button'
          onClick={() => onPick(kind)}
          className='inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-medium transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
        >
          <Icon className='size-3' />
          {label}
        </button>
      ))}
    </div>
  )
}
