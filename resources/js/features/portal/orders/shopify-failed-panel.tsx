import { toast } from 'sonner'
import { AlertTriangle, CheckCircle2, RefreshCw, Loader2, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
  useShopifySyncFailures,
  useShopifySyncFailureMutations,
  type ShopifySyncFailure,
} from '@/hooks/usePortal'

/**
 * Orders Shopify sent that never became orders here.
 *
 * They retry on their own — this panel exists so the merchant can see that an
 * order is stuck (rather than wondering why it never showed up), read why, and
 * push it through now instead of waiting out the backoff.
 */
export function ShopifyFailedPanel({ onChanged }: { onChanged?: () => void }) {
  const { failures, meta, loading, updateFilters, refresh } = useShopifySyncFailures({
    per_page: 25,
  })
  const { retry, retryAll, discard, loading: mutating } = useShopifySyncFailureMutations()

  const afterMutation = () => {
    refresh()
    onChanged?.()
  }

  const handleRetry = async (failure: ShopifySyncFailure) => {
    if (await retry(failure.id)) {
      toast.success('Retrying — the order will appear in your list if it goes through.')
      afterMutation()
    } else {
      toast.error('Could not retry this order.')
    }
  }

  const handleRetryAll = async () => {
    const count = await retryAll()
    if (count > 0) {
      toast.success(`Retrying ${count} order${count === 1 ? '' : 's'}.`)
      afterMutation()
    } else {
      toast.error('Could not retry these orders.')
    }
  }

  const handleDiscard = async (failure: ShopifySyncFailure) => {
    if (await discard(failure.id)) {
      toast.success('This order will no longer be retried.')
      afterMutation()
    } else {
      toast.error('Could not discard this order.')
    }
  }

  // The merchant does not need our exception text — they need to know whether
  // this is going to fix itself. Only the cause they can act on is spelled out.
  const describe = (failure: ShopifySyncFailure) =>
    failure.reason === 'no_connection'
      ? 'Arrived while the store was not connected'
      : 'Could not be imported'

  const retryState = (failure: ShopifySyncFailure) => {
    if (failure.status === 'abandoned') {
      return { label: 'Gave up — retry manually', tone: 'text-red-600 dark:text-red-400' }
    }

    if (!failure.next_attempt_at) {
      return { label: 'Retrying shortly', tone: 'text-muted-foreground' }
    }

    return {
      label: `Next try ${new Date(failure.next_attempt_at).toLocaleString()}`,
      tone: 'text-muted-foreground',
    }
  }

  return (
    <div className='space-y-3'>
      {failures.length > 0 && (
        <div className='flex items-center justify-between rounded-md border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-900 dark:bg-amber-950/40'>
          <div className='flex items-center gap-2 text-sm'>
            <AlertTriangle className='h-4 w-4 text-amber-600 dark:text-amber-400' />
            <span>
              These orders are in Shopify but not here yet. They retry automatically —
              you can also try again now.
            </span>
          </div>
          <Button size='sm' variant='outline' disabled={mutating} onClick={handleRetryAll}>
            {mutating ? (
              <Loader2 className='mr-2 h-4 w-4 animate-spin' />
            ) : (
              <RefreshCw className='mr-2 h-4 w-4' />
            )}
            Retry all
          </Button>
        </div>
      )}

      <div className='overflow-hidden rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Shopify order</TableHead>
              <TableHead>Store</TableHead>
              <TableHead>Why</TableHead>
              <TableHead className='text-center'>Tries</TableHead>
              <TableHead>Next attempt</TableHead>
              <TableHead className='text-right'>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: 4 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 6 }).map((_, j) => (
                    <TableCell key={j}>
                      <div className='h-4 w-full animate-pulse rounded bg-muted' />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : failures.length ? (
              failures.map((failure) => {
                const state = retryState(failure)

                return (
                  <TableRow key={failure.id}>
                    <TableCell className='font-medium'>
                      {failure.order_number || failure.shopify_order_id || '—'}
                    </TableCell>
                    <TableCell className='text-muted-foreground text-sm'>
                      {failure.shop_domain}
                    </TableCell>
                    <TableCell>
                      <div className='flex flex-col'>
                        <span className='text-sm'>{describe(failure)}</span>
                        <span className='text-muted-foreground text-xs'>
                          Received {new Date(failure.created_at).toLocaleString()}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell className='text-center'>
                      <Badge variant={failure.status === 'abandoned' ? 'destructive' : 'secondary'}>
                        {failure.attempts}
                      </Badge>
                    </TableCell>
                    <TableCell className={`text-sm ${state.tone}`}>{state.label}</TableCell>
                    <TableCell className='text-right'>
                      <div className='flex items-center justify-end gap-1.5'>
                        <Button
                          size='sm'
                          variant='outline'
                          className='h-7 text-xs'
                          disabled={mutating}
                          onClick={() => handleRetry(failure)}
                        >
                          <RefreshCw className='mr-1 h-3 w-3' />
                          Retry
                        </Button>
                        <Button
                          size='sm'
                          variant='ghost'
                          className='h-7 text-xs text-red-600 hover:text-red-600 dark:text-red-400'
                          disabled={mutating || failure.status === 'abandoned'}
                          onClick={() => handleDiscard(failure)}
                        >
                          <X className='mr-1 h-3 w-3' />
                          Stop retrying
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                )
              })
            ) : (
              <TableRow>
                <TableCell colSpan={6} className='h-32 text-center'>
                  <div className='text-muted-foreground flex flex-col items-center gap-2'>
                    <CheckCircle2 className='h-8 w-8 text-emerald-500/40' />
                    <p className='text-sm'>Every Shopify order has synced.</p>
                    <p className='text-xs'>
                      Anything that fails to import will show up here with a retry.
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
