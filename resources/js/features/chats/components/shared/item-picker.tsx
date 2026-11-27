import { useEffect, useState } from 'react'
import { Check, Loader2, Search } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { MAX_SHARED_ITEMS } from '../../constants'
import type { ChatItemSources, ChatSharedItem } from '../../types'
import { ItemThumb, itemSummary } from './shared-items'

type Tab = 'order' | 'product'

const same = (a: ChatSharedItem, b: ChatSharedItem) => a.type === b.type && a.id === b.id

/**
 * Pick the orders and inventory products a message is about. Inventory is
 * offered only to accounts that have it.
 */
export function ItemPicker({
  open,
  onOpenChange,
  sources,
  selected,
  onAttach,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  sources: ChatItemSources
  selected: ChatSharedItem[]
  onAttach: (items: ChatSharedItem[]) => void
}) {
  const [tab, setTab] = useState<Tab>('order')
  const [q, setQ] = useState('')
  const [options, setOptions] = useState<ChatSharedItem[]>([])
  const [loading, setLoading] = useState(false)
  const [failed, setFailed] = useState(false)
  const [picked, setPicked] = useState<ChatSharedItem[]>(selected)
  const search = tab === 'product' ? sources.products : sources.orders

  useEffect(() => {
    if (!open) return
    setPicked(selected)
    setQ('')
    setTab('order')
    // Only when it opens: what is ticked is this dialog's own until "Attach".
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  useEffect(() => {
    if (!open || !search) return
    let cancelled = false
    setLoading(true)
    setFailed(false)
    const timer = setTimeout(() => {
      search(q)
        .then((rows) => !cancelled && setOptions(rows))
        .catch(() => {
          if (cancelled) return
          setOptions([])
          setFailed(true)
        })
        .finally(() => !cancelled && setLoading(false))
    }, 250)
    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [open, q, search])

  const full = picked.length >= MAX_SHARED_ITEMS
  const toggle = (item: ChatSharedItem) =>
    setPicked((current) =>
      current.some((x) => same(x, item))
        ? current.filter((x) => !same(x, item))
        : current.length < MAX_SHARED_ITEMS
          ? [...current, item]
          : current
    )

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='gap-3 sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Attach {sources.products ? 'orders or inventory' : 'orders'}</DialogTitle>
          <DialogDescription>Choose what your message is about. Up to {MAX_SHARED_ITEMS} at a time.</DialogDescription>
        </DialogHeader>

        {sources.products && (
          <div className='grid grid-cols-2 gap-1 rounded-lg bg-muted p-1' role='tablist'>
            {(
              [
                ['order', 'Orders'],
                ['product', 'Inventory'],
              ] as const
            ).map(([value, label]) => (
              <button
                key={value}
                type='button'
                role='tab'
                aria-selected={tab === value}
                onClick={() => {
                  setTab(value)
                  setOptions([])
                  setQ('')
                }}
                className={cn(
                  'rounded-md py-1 text-xs font-medium transition-colors',
                  tab === value ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                {label}
              </button>
            ))}
          </div>
        )}

        <div className='relative'>
          <Search className='absolute start-2.5 top-2.5 size-4 text-muted-foreground' />
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={tab === 'order' ? 'Search by order number' : 'Search by name, SKU or code'}
            className='h-9 ps-8 text-sm'
          />
        </div>

        <div className='-mx-1 h-64 overflow-y-auto px-1'>
          {loading ? (
            <div className='flex h-full items-center justify-center'>
              <Loader2 className='size-5 animate-spin text-muted-foreground' />
            </div>
          ) : options.length === 0 ? (
            <p className='flex h-full items-center justify-center text-sm text-muted-foreground'>
              {failed ? 'Could not load them. Try again.' : tab === 'order' ? 'No orders found.' : 'No products found.'}
            </p>
          ) : (
            <ul className='space-y-1'>
              {options.map((item) => {
                const on = picked.some((x) => same(x, item))
                return (
                  <li key={`${item.type}-${item.id}`}>
                    <button
                      type='button'
                      onClick={() => toggle(item)}
                      disabled={!on && full}
                      aria-pressed={on}
                      className={cn(
                        'flex w-full items-center gap-2.5 rounded-md border p-2 text-start transition-colors disabled:opacity-50',
                        on ? 'border-[#f26722] bg-[#f26722]/5' : 'border-transparent hover:bg-muted'
                      )}
                    >
                      <ItemThumb item={item} />
                      <span className='min-w-0 flex-1'>
                        <span className='block truncate text-sm font-medium'>{item.title}</span>
                        <span className='block truncate text-xs text-muted-foreground'>{itemSummary(item)}</span>
                      </span>
                      <span
                        className={cn(
                          'flex size-4 shrink-0 items-center justify-center rounded-sm border',
                          on && 'border-[#f26722] bg-[#f26722] text-white'
                        )}
                      >
                        {on && <Check className='size-3' />}
                      </span>
                    </button>
                  </li>
                )
              })}
            </ul>
          )}
        </div>

        <DialogFooter className='items-center sm:justify-between'>
          <span className='text-xs text-muted-foreground'>
            {picked.length} of {MAX_SHARED_ITEMS} selected
          </span>
          <div className='flex gap-2'>
            <Button type='button' variant='outline' size='sm' onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button
              type='button'
              size='sm'
              onClick={() => {
                onAttach(picked)
                onOpenChange(false)
              }}
            >
              Attach
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
