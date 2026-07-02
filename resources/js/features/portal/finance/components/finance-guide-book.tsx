import { BookOpen } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'

interface Charges {
  delivery?: number | null
  return?: number | null
  cod?: number | null
  warehousing?: number | null
  call_confirmation?: number | null
  vat?: number | null
  other?: number | null
}

interface Props {
  clientType: string[]
  charges: Charges
}

export function FinanceGuideBook({ clientType, charges }: Props) {
  const isDropshipper = clientType.includes('dropshipper')
  const isFulfilment  = clientType.includes('fulfilment')

  const delivery    = charges?.delivery ?? 0
  const callConfirm = charges?.call_confirmation ?? 0
  const cod         = charges?.cod ?? 0
  const vat         = charges?.vat ?? 0
  const other       = charges?.other ?? 0

  const exampleRevenue     = 150
  const exampleProductCost = isDropshipper ? 100 : 0
  const exampleCharges     = delivery + callConfirm + cod + other + (exampleRevenue * vat / 100)
  const exampleProfit      = exampleRevenue - exampleProductCost - exampleCharges

  return (
    <Dialog>
      <DialogTrigger asChild>
        <Button variant='outline' size='sm' className='gap-2'>
          <BookOpen className='h-4 w-4' />
          How is this calculated?
        </Button>
      </DialogTrigger>
      <DialogContent className='max-w-lg'>
        <DialogHeader>
          <DialogTitle>Profit Calculation Guide</DialogTitle>
        </DialogHeader>

        <div className='space-y-5 text-sm'>
          {/* Account type */}
          <div className='rounded-lg border bg-muted/40 px-4 py-3'>
            <p className='text-xs font-medium text-muted-foreground mb-1'>Your account type</p>
            <p className='font-semibold capitalize'>{clientType.map((t) => t === 'fulfilment' ? 'Fulfillment' : t).join(' & ')}</p>
          </div>

          {/* Formula */}
          <div>
            <p className='font-medium mb-2'>Profit Formula</p>
            {isDropshipper && (
              <div className='rounded-lg border px-4 py-3 font-mono text-xs leading-6'>
                <p>Profit = <span className='text-blue-600 dark:text-blue-400'>Sold Price</span></p>
                <p className='pl-10'>− <span className='text-orange-600 dark:text-orange-400'>Product Cost</span> (KSA Drop catalog price)</p>
                {delivery > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>Delivery ({delivery} SAR)</span></p>}
                {callConfirm > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>Call Confirmation ({callConfirm} SAR)</span></p>}
                {cod > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>COD Fee ({cod} SAR)</span></p>}
                {vat > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>VAT ({vat}%)</span></p>}
                {other > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>Other ({other} SAR)</span></p>}
              </div>
            )}
            {isFulfilment && !isDropshipper && (
              <div className='rounded-lg border px-4 py-3 font-mono text-xs leading-6'>
                <p>Profit = <span className='text-blue-600 dark:text-blue-400'>Sold Price</span></p>
                {delivery > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>Delivery ({delivery} SAR)</span></p>}
                {callConfirm > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>Call Confirmation ({callConfirm} SAR)</span></p>}
                {cod > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>COD Fee ({cod} SAR)</span></p>}
                {vat > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>VAT ({vat}%)</span></p>}
                {other > 0 && <p className='pl-10'>− <span className='text-muted-foreground'>Other ({other} SAR)</span></p>}
              </div>
            )}
          </div>

          {/* Example */}
          <div>
            <p className='font-medium mb-2'>Example</p>
            <div className='rounded-lg border divide-y text-xs'>
              <div className='flex justify-between px-4 py-2'>
                <span className='text-muted-foreground'>Customer paid</span>
                <span className='font-medium'>SAR {exampleRevenue}</span>
              </div>
              {isDropshipper && (
                <div className='flex justify-between px-4 py-2'>
                  <span className='text-muted-foreground'>Product cost (catalog price)</span>
                  <span className='text-orange-600 dark:text-orange-400'>− SAR {exampleProductCost}</span>
                </div>
              )}
              {delivery > 0 && (
                <div className='flex justify-between px-4 py-2'>
                  <span className='text-muted-foreground'>Delivery fee</span>
                  <span className='text-muted-foreground'>− SAR {delivery}</span>
                </div>
              )}
              {callConfirm > 0 && (
                <div className='flex justify-between px-4 py-2'>
                  <span className='text-muted-foreground'>Call confirmation fee</span>
                  <span className='text-muted-foreground'>− SAR {callConfirm}</span>
                </div>
              )}
              {cod > 0 && (
                <div className='flex justify-between px-4 py-2'>
                  <span className='text-muted-foreground'>COD fee</span>
                  <span className='text-muted-foreground'>− SAR {cod}</span>
                </div>
              )}
              {vat > 0 && (
                <div className='flex justify-between px-4 py-2'>
                  <span className='text-muted-foreground'>VAT ({vat}% of {exampleRevenue})</span>
                  <span className='text-muted-foreground'>− SAR {(exampleRevenue * vat / 100).toFixed(2)}</span>
                </div>
              )}
              {other > 0 && (
                <div className='flex justify-between px-4 py-2'>
                  <span className='text-muted-foreground'>Other charges</span>
                  <span className='text-muted-foreground'>− SAR {other}</span>
                </div>
              )}
              <div className='flex justify-between px-4 py-2 bg-muted/40'>
                <span className='font-semibold'>Your Profit</span>
                <span className={`font-bold ${exampleProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                  SAR {exampleProfit.toFixed(2)}
                </span>
              </div>
            </div>
          </div>

          {/* Balance note */}
          <p className='text-xs text-muted-foreground'>
            <strong>Balance Owed</strong> = Your total profit minus the amounts already transferred to you by KSA Drop.
          </p>
        </div>
      </DialogContent>
    </Dialog>
  )
}
