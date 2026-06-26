import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Check, X, ShoppingBag, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { OrdersPagination } from '@/features/orders/components/orders-pagination'
import {
  useShopifyPendingOrders,
  useShopifyPendingMutations,
} from '@/hooks/usePortal'

interface PendingOrder {
  id: number
  order_number: string
  customer_name: string | null
  customer_email: string | null
  total: string | number
  currency: string
  created_at: string
  items?: { id: number }[]
}

export function ShopifyQueuePanel({ onChanged }: { onChanged?: () => void }) {
  const { orders, meta, loading, updateFilters, refresh } = useShopifyPendingOrders({
    per_page: 25,
  })
  const { submit, submitBulk, dismiss, loading: mutating } = useShopifyPendingMutations()
  const [selected, setSelected] = useState<Set<number>>(new Set())

  const pending: PendingOrder[] = orders

  const allSelected = pending.length > 0 && selected.size === pending.length
  const someSelected = selected.size > 0 && selected.size < pending.length

  const toggleAll = () => {
    setSelected(allSelected ? new Set() : new Set(pending.map((o) => o.id)))
  }

  const toggleOne = (id: number) => {
    setSelected((prev) => {
      const next = new Set(prev)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })
  }

  const afterMutation = () => {
    setSelected(new Set())
    refresh()
    onChanged?.()
  }

  const handleSubmit = async (id: number) => {
    if (await submit(id)) {
      toast.success('Order submitted to your orders list.')
      afterMutation()
    } else {
      toast.error('Could not submit order.')
    }
  }

  const handleDismiss = async (id: number) => {
    if (await dismiss(id)) {
      toast.success('Order dismissed.')
      afterMutation()
    } else {
      toast.error('Could not dismiss order.')
    }
  }

  const handleSubmitSelected = async () => {
    const count = await submitBulk(Array.from(selected))
    if (count > 0) {
      toast.success(`${count} order${count === 1 ? '' : 's'} submitted.`)
      afterMutation()
    } else {
      toast.error('Could not submit the selected orders.')
    }
  }

  const formatMoney = (value: string | number, currency: string) =>
    `${currency || 'SAR'} ${Number(value || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`

  const headerCheckboxState = useMemo(
    () => (allSelected ? true : someSelected ? 'indeterminate' : false),
    [allSelected, someSelected]
  )

  return (
    <div className='space-y-3'>
      {/* Bulk action bar */}
      {selected.size > 0 && (
        <div className='flex items-center justify-between rounded-md border bg-muted/40 px-3 py-2'>
          <span className='text-sm font-medium'>
            {selected.size} selected
          </span>
          <Button size='sm' disabled={mutating} onClick={handleSubmitSelected}>
            {mutating ? (
              <Loader2 className='mr-2 h-4 w-4 animate-spin' />
            ) : (
              <Check className='mr-2 h-4 w-4' />
            )}
            Submit selected
          </Button>
        </div>
      )}

      <div className='overflow-hidden rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className='w-10'>
                <Checkbox
                  checked={headerCheckboxState}
                  onCheckedChange={toggleAll}
                  aria-label='Select all'
                  disabled={pending.length === 0}
                />
              </TableHead>
              <TableHead>Order #</TableHead>
              <TableHead>Customer</TableHead>
              <TableHead className='text-center'>Items</TableHead>
              <TableHead>Total</TableHead>
              <TableHead>Received</TableHead>
              <TableHead className='text-right'>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 7 }).map((_, j) => (
                    <TableCell key={j}>
                      <div className='h-4 w-full animate-pulse rounded bg-muted' />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : pending.length ? (
              pending.map((order) => (
                <TableRow key={order.id} data-state={selected.has(order.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.has(order.id)}
                      onCheckedChange={() => toggleOne(order.id)}
                      aria-label={`Select order ${order.order_number}`}
                    />
                  </TableCell>
                  <TableCell className='font-medium'>{order.order_number}</TableCell>
                  <TableCell>
                    <div className='flex flex-col'>
                      <span>{order.customer_name || 'Guest'}</span>
                      {order.customer_email && (
                        <span className='text-muted-foreground text-xs'>
                          {order.customer_email}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className='text-center tabular-nums'>
                    {order.items?.length ?? 0}
                  </TableCell>
                  <TableCell className='tabular-nums'>
                    {formatMoney(order.total, order.currency)}
                  </TableCell>
                  <TableCell className='text-muted-foreground text-sm'>
                    {new Date(order.created_at).toLocaleDateString()}
                  </TableCell>
                  <TableCell className='text-right'>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button
                        size='sm'
                        variant='outline'
                        className='h-7 text-xs'
                        disabled={mutating}
                        onClick={() => handleSubmit(order.id)}
                      >
                        <Check className='mr-1 h-3 w-3' />
                        Submit
                      </Button>
                      <Button
                        size='sm'
                        variant='ghost'
                        className='h-7 text-xs text-red-600 hover:text-red-600 dark:text-red-400'
                        disabled={mutating}
                        onClick={() => handleDismiss(order.id)}
                      >
                        <X className='mr-1 h-3 w-3' />
                        Dismiss
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={7} className='h-32 text-center'>
                  <div className='text-muted-foreground flex flex-col items-center gap-2'>
                    <ShoppingBag className='text-muted-foreground/30 h-8 w-8' />
                    <p className='text-sm'>No orders pending review.</p>
                    <p className='text-xs'>
                      New Shopify orders will appear here for you to approve.
                    </p>
                  </div>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {meta && meta.last_page > 1 && (
        <OrdersPagination
          meta={meta}
          onPageChange={(page) => updateFilters({ page })}
          onPageSizeChange={(perPage) => updateFilters({ per_page: perPage, page: 1 })}
        />
      )}
    </div>
  )
}
