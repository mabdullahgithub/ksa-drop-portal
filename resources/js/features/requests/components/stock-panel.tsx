import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { ArrowRight, Check, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { usePermissions } from '@/hooks/use-permissions'
import {
  type RequestClient,
  type RequestFilters,
  approveStockRequest,
  rejectInventoryRequest,
  useRequestQueue,
} from '@/hooks/useInventoryRequests'
import type { InventoryRequest } from '@/types/client'
import { BulkBar } from './bulk-bar'
import {
  ProductCell,
  QueueRows,
  REQUEST_STATUSES,
  RejectDialog,
  RequestStatusBadge,
  RequestedAt,
  RequestsPager,
  RequestsToolbar,
  StockChange,
} from './request-parts'
import { StockApproveDialog } from './stock-approve-dialog'

type Props = {
  clients: RequestClient[]
  initialFilters: Partial<RequestFilters>
  /** Something was decided: the tab counts are stale. */
  onChanged: () => void
  /** The page locked again while open: back to the PIN prompt. */
  onLocked: () => void
}

/**
 * Stock changes clients asked for on verified products. Approving one is
 * where the person types the count the warehouse really received.
 */
export function StockPanel({ clients, initialFilters, onChanged, onLocked }: Props) {
  const { can } = usePermissions()
  // Each button is a permission of its own.
  const canApprove = can('approve stock requests')
  const canReject = can('reject stock requests')
  const canBulkApprove = can('bulk approve stock requests')
  const canBulkReject = can('bulk reject stock requests')

  const { rows, meta, loading, error, filters, updateFilters, refresh } = useRequestQueue<InventoryRequest>('stock', initialFilters, onLocked)
  const [approving, setApproving] = useState<InventoryRequest | null>(null)
  const [rejecting, setRejecting] = useState<InventoryRequest | null>(null)
  const [selected, setSelected] = useState<number[]>([])

  const waiting = filters.status === 'pending'
  // Only what is still waiting can be decided, one at a time or several.
  const selectable = waiting && (canBulkApprove || canBulkReject)
  const hasActions = canApprove || canReject
  const columns = 6 + (selectable ? 1 : 0) + (hasActions ? 1 : 0)

  // A selection belongs to the rows on screen.
  useEffect(() => {
    setSelected([])
  }, [rows])

  const done = () => {
    refresh()
    onChanged()
  }

  const approve = async (units: number, markInStock: boolean) => {
    if (!approving) return
    try {
      const { request } = await approveStockRequest(approving, units, markInStock)
      toast.success(`Stock of "${request.product?.name}" is now ${request.stock_after}`)
      setApproving(null)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to approve')
      // Someone else decided it, or the client changed it: show what is there now.
      setApproving(null)
    }
    done()
  }

  const reject = async (reason: string) => {
    if (!rejecting) return
    try {
      await rejectInventoryRequest(rejecting, reason)
      toast.success('Stock update rejected')
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to reject')
    }
    setRejecting(null)
    done()
  }

  const allSelected = rows.length > 0 && selected.length === rows.length
  const toggle = (id: number) =>
    setSelected((current) => (current.includes(id) ? current.filter((other) => other !== id) : [...current, id]))

  return (
    <div className='space-y-4'>
      <RequestsToolbar filters={filters} onChange={updateFilters} clients={clients} statuses={REQUEST_STATUSES} onRefresh={refresh} loading={loading} />

      {selectable && (
        <BulkBar
          type='stock'
          ids={selected}
          canApprove={canBulkApprove}
          canReject={canBulkReject}
          approveNote='Each is approved as requested: the count the client stated is taken as received. Approve one at a time to enter a different count.'
          onClear={() => setSelected([])}
          onDone={done}
        />
      )}

      <div className='rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              {selectable && (
                <TableHead className='w-10'>
                  <Checkbox
                    checked={allSelected}
                    onCheckedChange={(checked) => setSelected(checked ? rows.map((row) => row.id) : [])}
                    aria-label='Select all'
                  />
                </TableHead>
              )}
              <TableHead>Client</TableHead>
              <TableHead>Product</TableHead>
              <TableHead>Asked</TableHead>
              <TableHead>Stock now</TableHead>
              <TableHead>Requested</TableHead>
              <TableHead>Status</TableHead>
              {hasActions && <TableHead className='w-48' />}
            </TableRow>
          </TableHeader>
          <TableBody>
            <QueueRows loading={loading} error={error} empty={rows.length === 0} columns={columns} waiting={waiting} onRetry={refresh}>
              {rows.map((request) => {
                const live = request.product?.quantity ?? null
                const moved = request.status === 'pending' && live !== null && live !== request.stock_at_request

                return (
                  <TableRow key={request.id}>
                    {selectable && (
                      <TableCell>
                        <Checkbox
                          checked={selected.includes(request.id)}
                          onCheckedChange={() => toggle(request.id)}
                          aria-label='Select request'
                        />
                      </TableCell>
                    )}
                    <TableCell className='text-sm'>{request.client?.company_name ?? '—'}</TableCell>
                    <TableCell>
                      <ProductCell product={request.product} />
                    </TableCell>
                    <TableCell>
                      <div className='flex items-center gap-1.5 whitespace-nowrap text-sm'>
                        {request.stock_at_request}
                        <ArrowRight className='h-3.5 w-3.5 text-muted-foreground' />
                        {request.requested_quantity}
                        <StockChange value={request.change} />
                      </div>
                    </TableCell>
                    <TableCell>
                      <span className={moved ? 'font-medium text-yellow-700 dark:text-yellow-500' : ''} title={moved ? 'Stock moved since the client asked' : undefined}>
                        {live ?? '—'}
                      </span>
                    </TableCell>
                    <TableCell>
                      <RequestedAt value={request.created_at} by={request.requested_by} waiting={request.status === 'pending'} />
                    </TableCell>
                    <TableCell>
                      <RequestStatusBadge status={request.status} />
                      {request.status === 'approved' && (
                        <div className='mt-1 text-xs text-muted-foreground'>
                          {(request.received_quantity ?? 0) < 0 ? 'Took off' : 'Received'}{' '}
                          {Math.abs(request.received_quantity ?? 0)} · {request.stock_before} → {request.stock_after}
                        </div>
                      )}
                      {request.status === 'rejected' && request.rejection_reason && (
                        <div className='mt-1 max-w-[220px] text-xs text-muted-foreground'>{request.rejection_reason}</div>
                      )}
                      {request.reviewed_by && (
                        <div className='mt-0.5 text-xs text-muted-foreground'>by {request.reviewed_by}</div>
                      )}
                    </TableCell>
                    {hasActions && (
                      <TableCell>
                        {request.status === 'pending' && (
                          <div className='flex justify-end gap-2'>
                            {canApprove && (
                              <Button size='sm' className='h-8 gap-1.5' onClick={() => setApproving(request)}>
                                <Check className='h-4 w-4' />
                                Approve
                              </Button>
                            )}
                            {canReject && (
                              <Button variant='outline' size='sm' className='h-8 gap-1.5' onClick={() => setRejecting(request)}>
                                <X className='h-4 w-4' />
                                Reject
                              </Button>
                            )}
                          </div>
                        )}
                      </TableCell>
                    )}
                  </TableRow>
                )
              })}
            </QueueRows>
          </TableBody>
        </Table>
      </div>

      <RequestsPager meta={meta} onPage={(page) => updateFilters({ page })} />

      <StockApproveDialog
        request={approving}
        onOpenChange={(open) => {
          if (!open) setApproving(null)
        }}
        onConfirm={approve}
      />

      <RejectDialog
        open={!!rejecting}
        onOpenChange={(open) => {
          if (!open) setRejecting(null)
        }}
        title='Reject stock update'
        description={
          <>
            {rejecting?.client?.company_name} asked to change stock of <strong>{rejecting?.product?.name}</strong> from{' '}
            {rejecting?.stock_at_request} to {rejecting?.requested_quantity}. The stock stays as it is.
          </>
        }
        onConfirm={reject}
      />
    </div>
  )
}
