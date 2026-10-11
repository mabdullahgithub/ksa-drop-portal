import { useState } from 'react'
import { AlertTriangle, ArrowRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { InventoryRequest, InventoryRequestField } from '@/types/client'
import { ProductCell, RequestStatusBadge } from './request-parts'

export const FIELD_LABELS: Record<InventoryRequestField, string> = {
  name: 'Name',
  sku: 'SKU',
  description: 'Description',
  unit_price: 'Unit price',
  notes: 'Notes',
}

const show = (field: InventoryRequestField, value: string | null | undefined) => {
  if (value === null || value === undefined || value === '') return <span className='text-muted-foreground'>empty</span>
  return field === 'unit_price' ? `SAR ${parseFloat(value).toFixed(2)}` : value
}

type Props = {
  request: InventoryRequest | null
  canApprove: boolean
  canReject: boolean
  onOpenChange: (open: boolean) => void
  /** Resolves when the approval went through; the dialog then closes. */
  onApprove: () => Promise<void>
  onReject: () => void
}

/**
 * A details change, field by field: what the product holds now beside what
 * the client wants it to hold. Approving applies all of it or none.
 */
export function DetailsReviewDialog({ request, canApprove, canReject, onOpenChange, onApprove, onReject }: Props) {
  const [sending, setSending] = useState(false)

  const pending = request?.status === 'pending'
  const fields = Object.keys(request?.changes ?? {}) as InventoryRequestField[]
  const removed = new Set(request?.remove_image_ids ?? [])
  const productImages = request?.product?.images ?? []
  const hasImages = (request?.images.length ?? 0) > 0 || removed.size > 0

  // A decided request shows what was true when it was asked; one still
  // waiting is checked against the product as it is today.
  const before = pending ? request?.current : request?.original
  // Someone on the team edited the product after the client asked.
  const stale = (field: InventoryRequestField) =>
    pending && (request?.original?.[field] ?? null) !== (request?.current?.[field] ?? null)

  const approve = async () => {
    if (sending) return
    setSending(true)
    try {
      await onApprove()
    } finally {
      setSending(false)
    }
  }

  return (
    <Dialog open={!!request} onOpenChange={(next) => !sending && onOpenChange(next)}>
      <DialogContent className='max-h-[min(90vh,44rem)] max-w-2xl overflow-y-auto'>
        <DialogHeader>
          <DialogTitle className='flex items-center gap-2'>
            Details change
            {request && <RequestStatusBadge status={request.status} />}
          </DialogTitle>
          <DialogDescription>
            Asked by {request?.client?.company_name}
            {request?.requested_by ? ` (${request.requested_by})` : ''}. Approving applies every change below.
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-4'>
          <div className='rounded-lg border bg-muted/30 p-3'>
            <ProductCell product={request?.product ?? null} />
          </div>

          {fields.length > 0 && (
            <div className='overflow-hidden rounded-md border text-sm'>
              <div className='grid grid-cols-[7rem_1fr_1.25rem_1fr] gap-2 border-b bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground'>
                <span>Field</span>
                <span>{pending ? 'Now' : 'Before'}</span>
                <span />
                <span>Asked for</span>
              </div>
              {fields.map((field) => (
                <div key={field} className='grid grid-cols-[7rem_1fr_1.25rem_1fr] gap-2 border-b px-3 py-2 last:border-b-0'>
                  <span className='text-muted-foreground'>{FIELD_LABELS[field]}</span>
                  <span className='min-w-0 break-words text-muted-foreground line-through decoration-muted-foreground/40'>
                    {show(field, before?.[field])}
                    {stale(field) && (
                      <span className='mt-0.5 block text-xs text-yellow-700 no-underline dark:text-yellow-500'>
                        changed since the client asked
                      </span>
                    )}
                  </span>
                  <ArrowRight className='mt-0.5 h-4 w-4 text-muted-foreground' />
                  <span className='min-w-0 break-words font-medium'>{show(field, request?.changes[field])}</span>
                </div>
              ))}
            </div>
          )}

          {hasImages && (
            <div className='space-y-2'>
              <p className='text-sm font-medium'>Images</p>
              <div className='flex flex-wrap gap-2'>
                {productImages.map((image) => (
                  <div
                    key={`current-${image.id}`}
                    className={`relative h-20 w-20 overflow-hidden rounded-md border ${removed.has(image.id) ? 'border-destructive opacity-50' : ''}`}
                  >
                    <img src={image.url} alt='' className='h-full w-full object-cover' />
                    {removed.has(image.id) && (
                      <span className='absolute inset-x-0 bottom-0 bg-destructive py-0.5 text-center text-[10px] font-medium text-destructive-foreground'>
                        Remove
                      </span>
                    )}
                  </div>
                ))}
                {request?.images.map((image) => (
                  <div key={`new-${image.id}`} className='relative h-20 w-20 overflow-hidden rounded-md border border-green-500'>
                    <img src={image.url} alt='' className='h-full w-full object-cover' />
                    <span className='absolute inset-x-0 bottom-0 bg-green-600 py-0.5 text-center text-[10px] font-medium text-white'>
                      New
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {pending && 'sku' in (request?.changes ?? {}) && (
            <p className='flex items-start gap-2 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-xs text-yellow-900 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300'>
              <AlertTriangle className='mt-0.5 h-3.5 w-3.5 shrink-0' />
              <span>
                The SKU changes. Orders find this product's stock by SKU, so orders still carrying the old one
                will no longer match it.
                {request?.sku_in_use && (
                  <strong className='mt-1 block'>
                    Another product of this client already uses the new SKU; orders would match the older of the two.
                  </strong>
                )}
              </span>
            </p>
          )}

          {request?.status === 'rejected' && request.rejection_reason && (
            <p className='rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300'>
              <span className='block text-xs font-medium uppercase tracking-wide'>Rejection reason</span>
              {request.rejection_reason}
            </p>
          )}
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)} disabled={sending}>
            {pending && (canApprove || canReject) ? 'Cancel' : 'Close'}
          </Button>
          {pending && canReject && (
            <Button variant='destructive' onClick={onReject} disabled={sending}>
              Reject
            </Button>
          )}
          {pending && canApprove && (
            <Button onClick={approve} disabled={sending}>
              {sending ? 'Approving…' : 'Approve'}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
