import { useEffect, useState } from 'react'
import { AlertTriangle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { InventoryRequest } from '@/types/client'
import { ProductCell, StockChange } from './request-parts'

type Props = {
  request: InventoryRequest | null
  onOpenChange: (open: boolean) => void
  /** Resolves when the approval went through; the dialog then closes. */
  onConfirm: (units: number, markInStock: boolean) => Promise<void>
}

/**
 * Approving a stock update: the person types the count the warehouse really
 * received, which is added to the stock as it stands now — not to what it was
 * when the client asked.
 */
export function StockApproveDialog({ request, onOpenChange, onConfirm }: Props) {
  const [units, setUnits] = useState('')
  const [markInStock, setMarkInStock] = useState(false)
  const [sending, setSending] = useState(false)

  const change = request?.change ?? 0
  const removing = change < 0
  const live = request?.product?.quantity ?? 0
  const flaggedOut = !!request?.product?.is_out_of_stock

  useEffect(() => {
    if (!request) return
    setUnits(String(Math.abs(request.change ?? 0)))
    // Stock coming in for a product marked out of stock: most likely back in.
    setMarkInStock(!!request.product?.is_out_of_stock && (request.change ?? 0) > 0)
  }, [request])

  const count = Number(units)
  const valid = units.trim() !== '' && Number.isInteger(count) && count >= 1
  const after = valid ? live + (removing ? -count : count) : null
  const belowZero = after !== null && after < 0
  // A parcel scanned out or in since the client asked.
  const moved = request?.stock_at_request !== null && request?.stock_at_request !== undefined && request.stock_at_request !== live

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (sending || !valid || belowZero) return
    setSending(true)
    try {
      await onConfirm(count, markInStock)
    } finally {
      setSending(false)
    }
  }

  return (
    <Dialog open={!!request} onOpenChange={(next) => !sending && onOpenChange(next)}>
      <DialogContent className='max-w-md'>
        <DialogHeader>
          <DialogTitle>Approve stock update</DialogTitle>
          <DialogDescription>
            {request?.client?.company_name} asked to change stock from {request?.stock_at_request} to{' '}
            {request?.requested_quantity}.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className='space-y-4'>
          <div className='rounded-lg border bg-muted/30 p-3'>
            <ProductCell product={request?.product ?? null} />
          </div>

          <div className='grid grid-cols-3 gap-2 text-center'>
            <div className='rounded-md border p-2'>
              <div className='text-xs text-muted-foreground'>Stock now</div>
              <div className='text-lg font-semibold'>{live}</div>
            </div>
            <div className='rounded-md border p-2'>
              <div className='text-xs text-muted-foreground'>Client asked</div>
              <div className='text-lg'>
                <StockChange value={change} />
              </div>
            </div>
            <div className='rounded-md border p-2'>
              <div className='text-xs text-muted-foreground'>After approval</div>
              <div className={`text-lg font-semibold ${belowZero ? 'text-destructive' : ''}`}>{after ?? '—'}</div>
            </div>
          </div>

          {moved && (
            <p className='flex items-start gap-2 text-xs text-muted-foreground'>
              <AlertTriangle className='mt-0.5 h-3.5 w-3.5 shrink-0 text-yellow-600' />
              Stock was {request?.stock_at_request} when the client asked and is {live} now. The count below is
              added to the stock as it is now.
            </p>
          )}

          <div className='space-y-2'>
            <Label htmlFor='stock-request-units'>
              {removing ? 'Units to take off' : 'Units received'} <span className='text-destructive'>*</span>
            </Label>
            <Input
              id='stock-request-units'
              type='number'
              min={1}
              step={1}
              value={units}
              onChange={(e) => setUnits(e.target.value)}
              disabled={sending}
              autoFocus
            />
            <p className='text-xs text-muted-foreground'>
              {removing
                ? 'How many units really leave this product’s stock.'
                : 'The count the warehouse actually received. It can differ from what the client stated.'}
            </p>
            {belowZero && <p className='text-xs text-destructive'>Only {live} in stock.</p>}
          </div>

          {flaggedOut && !removing && (
            <div className='flex items-center gap-2'>
              <Checkbox
                id='stock-request-in-stock'
                checked={markInStock}
                onCheckedChange={(checked) => setMarkInStock(!!checked)}
                disabled={sending}
              />
              <Label htmlFor='stock-request-in-stock' className='cursor-pointer'>
                Mark back in stock
              </Label>
            </div>
          )}

          <DialogFooter>
            <Button type='button' variant='outline' onClick={() => onOpenChange(false)} disabled={sending}>
              Cancel
            </Button>
            <Button type='submit' disabled={sending || !valid || belowZero}>
              {sending ? 'Approving…' : 'Approve'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
