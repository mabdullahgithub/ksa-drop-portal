import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Eye } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { usePermissions } from '@/hooks/use-permissions'
import {
  type RequestClient,
  type RequestFilters,
  approveDetailsRequest,
  rejectInventoryRequest,
  useRequestQueue,
} from '@/hooks/useInventoryRequests'
import type { InventoryRequest, InventoryRequestField } from '@/types/client'
import { BulkBar } from './bulk-bar'
import { DetailsReviewDialog, FIELD_LABELS } from './details-review-dialog'
import {
  ProductCell,
  QueueRows,
  REQUEST_STATUSES,
  RejectDialog,
  RequestStatusBadge,
  RequestedAt,
  RequestsPager,
  RequestsToolbar,
} from './request-parts'

type Props = {
  clients: RequestClient[]
  initialFilters: Partial<RequestFilters>
  /** Something was decided: the tab counts are stale. */
  onChanged: () => void
  /** The page locked again while open: back to the PIN prompt. */
  onLocked: () => void
}

/** What a details request touches, as short labels: "Name", "SKU", "Images +2 −1". */
export function changeLabels(request: InventoryRequest): string[] {
  const labels = (Object.keys(request.changes) as InventoryRequestField[]).map((field) => FIELD_LABELS[field] ?? field)
  const added = request.images.length
  const removed = request.remove_image_ids.length

  if (added || removed) {
    labels.push(['Images', added ? `+${added}` : '', removed ? `−${removed}` : ''].filter(Boolean).join(' '))
  }

  return labels
}

/**
 * Name, SKU, price and image changes clients asked for on verified products.
 * Opening one shows the product as it is beside what was asked.
 */
export function DetailsPanel({ clients, initialFilters, onChanged, onLocked }: Props) {
  const { can } = usePermissions()
  // Each button is a permission of its own.
  const canApprove = can('approve detail requests')
  const canReject = can('reject detail requests')
  const canBulkApprove = can('bulk approve detail requests')
  const canBulkReject = can('bulk reject detail requests')

  const { rows, meta, loading, error, filters, updateFilters, refresh } = useRequestQueue<InventoryRequest>('details', initialFilters, onLocked)
  const [open, setOpen] = useState<InventoryRequest | null>(null)
  const [rejecting, setRejecting] = useState<InventoryRequest | null>(null)
  const [selected, setSelected] = useState<number[]>([])

  const waiting = filters.status === 'pending'
  const selectable = waiting && (canBulkApprove || canBulkReject)
  const columns = 6 + (selectable ? 1 : 0)

  // A selection belongs to the rows on screen.
  useEffect(() => {
    setSelected([])
  }, [rows])

  const done = () => {
    refresh()
    onChanged()
  }

  const approve = async () => {
    if (!open) return
    try {
      await approveDetailsRequest(open)
      toast.success(`Changes applied to "${open.product?.name}"`)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to approve')
    }
    setOpen(null)
    done()
  }

  const reject = async (reason: string) => {
    if (!rejecting) return
    try {
      await rejectInventoryRequest(rejecting, reason)
      toast.success('Details change rejected')
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
          type='details'
          ids={selected}
          canApprove={canBulkApprove}
          canReject={canBulkReject}
          approveNote='Every change in each request is applied to its product. Open a request first to see exactly what changes.'
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
              <TableHead>Changes</TableHead>
              <TableHead>Requested</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className='w-24' />
            </TableRow>
          </TableHeader>
          <TableBody>
            <QueueRows loading={loading} error={error} empty={rows.length === 0} columns={columns} waiting={waiting} onRetry={refresh}>
              {rows.map((request) => (
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
                    <div className='flex max-w-[260px] flex-wrap gap-1'>
                      {changeLabels(request).map((label) => (
                        <Badge key={label} variant='secondary' className='text-xs font-normal'>
                          {label}
                        </Badge>
                      ))}
                    </div>
                  </TableCell>
                  <TableCell>
                    <RequestedAt value={request.created_at} by={request.requested_by} waiting={request.status === 'pending'} />
                  </TableCell>
                  <TableCell>
                    <RequestStatusBadge status={request.status} />
                    {request.status === 'rejected' && request.rejection_reason && (
                      <div className='mt-1 max-w-[220px] text-xs text-muted-foreground'>{request.rejection_reason}</div>
                    )}
                    {request.reviewed_by && (
                      <div className='mt-0.5 text-xs text-muted-foreground'>by {request.reviewed_by}</div>
                    )}
                  </TableCell>
                  <TableCell>
                    <Button variant='outline' size='sm' className='h-8 gap-1.5' onClick={() => setOpen(request)}>
                      <Eye className='h-4 w-4' />
                      Open
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </QueueRows>
          </TableBody>
        </Table>
      </div>

      <RequestsPager meta={meta} onPage={(page) => updateFilters({ page })} />

      <DetailsReviewDialog
        request={open}
        canApprove={canApprove}
        canReject={canReject}
        onOpenChange={(next) => {
          if (!next) setOpen(null)
        }}
        onApprove={approve}
        onReject={() => {
          setRejecting(open)
          setOpen(null)
        }}
      />

      <RejectDialog
        open={!!rejecting}
        onOpenChange={(next) => {
          if (!next) setRejecting(null)
        }}
        title='Reject details change'
        description={
          <>
            {rejecting?.client?.company_name} asked to change <strong>{rejecting?.product?.name}</strong>. The product
            stays as it is.
          </>
        }
        onConfirm={reject}
      />
    </div>
  )
}
