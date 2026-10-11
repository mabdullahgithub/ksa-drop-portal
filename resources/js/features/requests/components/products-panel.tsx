import { useState } from 'react'
import { Link } from '@inertiajs/react'
import { toast } from 'sonner'
import { CheckCircle, Clock, ImageOff, PackageX, ShieldCheck, XCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { usePermissions } from '@/hooks/use-permissions'
import {
  type RequestClient,
  type RequestedProduct,
  type RequestFilters,
  reviewRequestedProduct,
  useRequestQueue,
} from '@/hooks/useInventoryRequests'
import { ClientProductVerifyDialog } from '@/features/client/components/client-product-verify-dialog'
import { QueueRows, RequestedAt, RequestsPager, RequestsToolbar } from './request-parts'

const STATUSES: Record<string, string> = {
  pending: 'Pending',
  verified: 'Verified',
  rejected: 'Rejected',
  all: 'All',
}

type Props = {
  clients: RequestClient[]
  initialFilters: Partial<RequestFilters>
  /** Something was decided: the tab counts are stale. */
  onChanged: () => void
  /** The page locked again while open: back to the PIN prompt. */
  onLocked: () => void
}

/**
 * Products clients added that nobody has verified yet, every client's in one
 * list. The review itself is the same dialog a client's Inventory tab opens.
 */
export function ProductsPanel({ clients, initialFilters, onChanged, onLocked }: Props) {
  const { can } = usePermissions()
  const canReview = can('review product requests')
  const canOpenClient = can('view client details')

  const { rows, meta, loading, error, filters, updateFilters, refresh } = useRequestQueue<RequestedProduct>('products', initialFilters, onLocked)
  const [target, setTarget] = useState<RequestedProduct | null>(null)
  const [saving, setSaving] = useState(false)

  const waiting = filters.status === 'pending'
  const columns = canReview ? 8 : 7

  const handleReview = async (
    action: 'verify' | 'reject' | 'pending',
    images: File[],
    rejectionReason: string,
    isOutOfStock: boolean,
    receivedQuantity: number | null
  ) => {
    if (!target || saving) return

    if (action === 'reject' && !rejectionReason.trim()) {
      toast.error('Give the reason for rejecting; the client sees it.')
      return
    }

    setSaving(true)
    try {
      await reviewRequestedProduct(target.id, action, images, rejectionReason, isOutOfStock, receivedQuantity)
      const labels = { verify: 'verified', reject: 'rejected', pending: 'kept pending' }
      toast.success(`"${target.name}" ${labels[action]}`)
      setTarget(null)
      refresh()
      onChanged()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to update product status')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className='space-y-4'>
      <RequestsToolbar filters={filters} onChange={updateFilters} clients={clients} statuses={STATUSES} onRefresh={refresh} loading={loading} />

      <div className='rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className='w-14' />
              <TableHead>Client</TableHead>
              <TableHead>Product</TableHead>
              <TableHead>Qty</TableHead>
              <TableHead>Unit Price</TableHead>
              <TableHead>Submitted</TableHead>
              <TableHead>Status</TableHead>
              {canReview && <TableHead className='w-24' />}
            </TableRow>
          </TableHeader>
          <TableBody>
            <QueueRows loading={loading} error={error} empty={rows.length === 0} columns={columns} waiting={waiting} onRetry={refresh}>
              {rows.map((product) => (
                <TableRow key={product.id}>
                  <TableCell>
                    <div className='flex h-10 w-10 items-center justify-center overflow-hidden rounded-md border bg-muted/30'>
                      {product.images.length > 0 ? (
                        <img src={product.images[0].url} alt='' className='h-full w-full object-cover' />
                      ) : (
                        <ImageOff className='h-4 w-4 text-muted-foreground/50' />
                      )}
                    </div>
                  </TableCell>
                  <TableCell className='text-sm'>
                    {canOpenClient ? (
                      <Link href={`/client/${product.client.id}`} className='hover:underline'>
                        {product.client.company_name}
                      </Link>
                    ) : (
                      product.client.company_name
                    )}
                  </TableCell>
                  <TableCell>
                    <div className='font-medium'>{product.name}</div>
                    <div className='mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground'>
                      <code className='rounded bg-muted px-1.5 py-0.5 font-semibold text-foreground'>{product.product_code}</code>
                      {product.sku && <span>SKU: {product.sku}</span>}
                    </div>
                  </TableCell>
                  <TableCell>{product.quantity}</TableCell>
                  <TableCell className='text-sm'>
                    {product.unit_price ? `SAR ${parseFloat(product.unit_price).toFixed(2)}` : '—'}
                  </TableCell>
                  <TableCell>
                    <RequestedAt value={product.created_at} waiting={product.verification_status === 'pending'} />
                  </TableCell>
                  <TableCell>
                    <div className='flex flex-wrap gap-1'>
                      {product.verification_status === 'verified' ? (
                        <Badge variant='outline' className='bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 text-xs'>
                          <CheckCircle className='mr-1 h-3 w-3' />
                          Verified
                        </Badge>
                      ) : product.verification_status === 'rejected' ? (
                        <Badge variant='outline' className='bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 text-xs'>
                          <XCircle className='mr-1 h-3 w-3' />
                          Rejected
                        </Badge>
                      ) : (
                        <Badge variant='outline' className='bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 text-xs'>
                          <Clock className='mr-1 h-3 w-3' />
                          Pending
                        </Badge>
                      )}
                      {product.is_out_of_stock && (
                        <Badge variant='outline' className='bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400 text-xs'>
                          <PackageX className='mr-1 h-3 w-3' />
                          Out of Stock
                        </Badge>
                      )}
                    </div>
                  </TableCell>
                  {canReview && (
                    <TableCell>
                      <Button variant='outline' size='sm' className='h-8' onClick={() => setTarget(product)}>
                        <ShieldCheck className='mr-1.5 h-4 w-4' />
                        Review
                      </Button>
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </QueueRows>
          </TableBody>
        </Table>
      </div>

      <RequestsPager meta={meta} onPage={(page) => updateFilters({ page })} />

      <ClientProductVerifyDialog
        product={target}
        open={!!target}
        onOpenChange={(open) => {
          if (!open) setTarget(null)
        }}
        onConfirm={handleReview}
        loading={saving}
      />
    </div>
  )
}
