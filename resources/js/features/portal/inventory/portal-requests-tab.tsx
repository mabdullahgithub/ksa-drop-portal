import { useState } from 'react'
import { format, parseISO } from 'date-fns'
import { toast } from 'sonner'
import { ArrowRight, Ban, CheckCircle, ChevronLeft, ChevronRight, Clock, XCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { EmptyState } from '@/components/empty-state'
import type { usePortalInventoryRequests } from '@/hooks/usePortal'
import type { InventoryRequest, InventoryRequestField, InventoryRequestStatus } from '@/types/client'

const FIELD_LABELS: Record<InventoryRequestField, string> = {
  name: 'Name',
  sku: 'SKU',
  description: 'Description',
  unit_price: 'Unit price',
  notes: 'Notes',
}

const STATUS: Record<InventoryRequestStatus, { label: string; className: string; icon: React.ElementType }> = {
  pending: { label: 'Waiting for approval', className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', icon: Clock },
  approved: { label: 'Approved', className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', icon: CheckCircle },
  rejected: { label: 'Rejected', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', icon: XCircle },
  cancelled: { label: 'Cancelled', className: 'bg-muted text-muted-foreground', icon: Ban },
}

const date = (value: string | null) => (value ? format(parseISO(value), 'd MMM yyyy') : '—')

/** What was asked for, in a line. */
function Asked({ request }: { request: InventoryRequest }) {
  if (request.type === 'stock') {
    const change = request.change ?? 0

    return (
      <div className='flex items-center gap-1.5 whitespace-nowrap text-sm'>
        {request.stock_at_request}
        <ArrowRight className='h-3.5 w-3.5 text-muted-foreground' />
        {request.requested_quantity}
        <span className={change < 0 ? 'font-semibold text-red-600 dark:text-red-400' : 'font-semibold text-green-600 dark:text-green-400'}>
          {change > 0 ? '+' : '−'}
          {Math.abs(change)}
        </span>
      </div>
    )
  }

  const fields = Object.keys(request.changes) as InventoryRequestField[]
  const images = request.images.length + request.remove_image_ids.length

  return (
    <div className='max-w-[320px] space-y-0.5 text-sm'>
      {fields.map((field) => (
        <div key={field} className='truncate'>
          <span className='text-muted-foreground'>{FIELD_LABELS[field]}:</span>{' '}
          {request.changes[field] ?? <span className='text-muted-foreground'>empty</span>}
        </div>
      ))}
      {images > 0 && <div className='text-muted-foreground'>Images changed</div>}
    </div>
  )
}

/** What became of it: the count received, or why it was turned down. */
function Result({ request }: { request: InventoryRequest }) {
  if (request.status === 'rejected') {
    return <span className='text-sm text-red-700 dark:text-red-400'>{request.rejection_reason || 'No reason was given.'}</span>
  }

  if (request.status === 'approved' && request.type === 'stock') {
    const units = Math.abs(request.received_quantity ?? 0)

    return (
      <span className='text-sm'>
        {(request.received_quantity ?? 0) < 0 ? `${units} taken off` : `${units} received`} · stock now{' '}
        {request.stock_after}
      </span>
    )
  }

  if (request.status === 'approved') return <span className='text-sm'>Changes are live</span>

  return <span className='text-muted-foreground'>—</span>
}

type Props = {
  queue: ReturnType<typeof usePortalInventoryRequests>
  /** A request was cancelled: the product list's badges are stale. */
  onChanged: () => void
}

/**
 * What the client asked to change on verified products, newest first, and
 * what the team decided.
 */
export function PortalRequestsTab({ queue, onChanged }: Props) {
  const { requests, meta, loading, status, setStatus, setPage, refresh, cancel } = queue
  const [cancelTarget, setCancelTarget] = useState<InventoryRequest | null>(null)
  const [cancelling, setCancelling] = useState(false)

  const handleCancel = async () => {
    if (!cancelTarget || cancelling) return
    setCancelling(true)
    const error = await cancel(cancelTarget.id)
    setCancelling(false)
    setCancelTarget(null)

    if (error) {
      toast.error(error)
    } else {
      toast.success('Request cancelled')
      onChanged()
    }
    refresh()
  }

  return (
    <div className='space-y-4'>
      <Select value={status} onValueChange={setStatus}>
        <SelectTrigger className='w-48'>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value='all'>All Requests</SelectItem>
          <SelectItem value='pending'>Waiting for approval</SelectItem>
          <SelectItem value='approved'>Approved</SelectItem>
          <SelectItem value='rejected'>Rejected</SelectItem>
          <SelectItem value='cancelled'>Cancelled</SelectItem>
        </SelectContent>
      </Select>

      <div className='overflow-hidden rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Asked</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Result</TableHead>
              <TableHead>Date</TableHead>
              <TableHead className='w-24' />
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: 4 }).map((_, row) => (
                <TableRow key={row}>
                  {Array.from({ length: 7 }).map((_, cell) => (
                    <TableCell key={cell}>
                      <div className='h-4 w-full animate-pulse rounded bg-muted' />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : requests.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className='h-32 text-center'>
                  <EmptyState
                    bot='square'
                    state='sleeping'
                    title={status === 'all' ? 'No requests yet.' : 'No requests with this status.'}
                    description={
                      status === 'all'
                        ? 'When you change a verified product, the change waits here until our team approves it.'
                        : undefined
                    }
                  />
                </TableCell>
              </TableRow>
            ) : (
              requests.map((request) => {
                const { label, className, icon: Icon } = STATUS[request.status]

                return (
                  <TableRow key={request.id}>
                    <TableCell>
                      <div className='font-medium'>{request.product?.name ?? '—'}</div>
                      {request.product && (
                        <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold'>
                          {request.product.product_code}
                        </code>
                      )}
                    </TableCell>
                    <TableCell className='text-sm'>{request.type === 'stock' ? 'Stock' : 'Details'}</TableCell>
                    <TableCell>
                      <Asked request={request} />
                    </TableCell>
                    <TableCell>
                      <Badge variant='outline' className={`text-xs ${className}`}>
                        <Icon className='mr-1 h-3 w-3' />
                        {label}
                      </Badge>
                    </TableCell>
                    <TableCell className='max-w-[260px]'>
                      <Result request={request} />
                    </TableCell>
                    <TableCell className='whitespace-nowrap text-sm text-muted-foreground'>
                      {date(request.created_at)}
                      {request.reviewed_at && <div className='text-xs'>decided {date(request.reviewed_at)}</div>}
                    </TableCell>
                    <TableCell>
                      {request.status === 'pending' && (
                        <Button variant='outline' size='sm' className='h-8' onClick={() => setCancelTarget(request)}>
                          Cancel
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                )
              })
            )}
          </TableBody>
        </Table>
      </div>

      {meta && meta.last_page > 1 && (
        <div className='flex items-center justify-end gap-2 text-sm text-muted-foreground'>
          <Button variant='outline' size='icon' className='h-8 w-8' disabled={meta.current_page <= 1} onClick={() => setPage(meta.current_page - 1)} aria-label='Previous page'>
            <ChevronLeft className='h-4 w-4' />
          </Button>
          <span>
            Page {meta.current_page} of {meta.last_page}
          </span>
          <Button variant='outline' size='icon' className='h-8 w-8' disabled={meta.current_page >= meta.last_page} onClick={() => setPage(meta.current_page + 1)} aria-label='Next page'>
            <ChevronRight className='h-4 w-4' />
          </Button>
        </div>
      )}

      <ConfirmDialog
        open={!!cancelTarget}
        onOpenChange={(open) => {
          if (!open) setCancelTarget(null)
        }}
        title='Cancel this request?'
        desc={`Your ${cancelTarget?.type === 'stock' ? 'stock update' : 'changes'} for "${cancelTarget?.product?.name ?? ''}" will not be reviewed. The product stays as it is.`}
        cancelBtnText='Keep it'
        confirmText='Cancel request'
        destructive
        isLoading={cancelling}
        handleConfirm={handleCancel}
      />
    </div>
  )
}
