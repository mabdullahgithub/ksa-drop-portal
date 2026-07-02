import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { ChevronLeft, ChevronRight, Eye } from 'lucide-react'

interface Payment {
  id: number
  amount: string
  message: string | null
  paid_at: string
  proof_url: string | null
  created_by: { id: number; name: string } | null
}

interface PaginatedPayments {
  data: Payment[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface Props {
  payments: PaginatedPayments | null | undefined
  loading: boolean
  onPageChange: (page: number) => void
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('en-GB', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function ProofDialog({ url }: { url: string }) {
  const [open, setOpen] = useState(false)
  const isPdf = url.toLowerCase().endsWith('.pdf')

  return (
    <>
      <Button variant='outline' size='sm' className='h-7 gap-1.5 text-xs' onClick={() => setOpen(true)}>
        <Eye className='h-3 w-3' />
        View
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className='max-w-3xl w-full'>
          <DialogHeader>
            <DialogTitle>Payment Proof</DialogTitle>
          </DialogHeader>
          <div className='mt-2 overflow-auto max-h-[70vh] flex items-center justify-center rounded border bg-muted/20'>
            {isPdf ? (
              <iframe src={url} className='w-full h-[65vh]' title='Payment Proof' />
            ) : (
              <img src={url} alt='Payment Proof' className='max-w-full max-h-[65vh] object-contain' />
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}

export function FinancePaymentsTable({ payments, loading, onPageChange }: Props) {
  if (loading && !payments) {
    return (
      <div className='flex items-center justify-center py-8 text-sm text-muted-foreground'>
        Loading payments...
      </div>
    )
  }

  if (!payments || payments.data.length === 0) {
    return (
      <div className='flex items-center justify-center py-8 text-sm text-muted-foreground'>
        No payments recorded yet.
      </div>
    )
  }

  return (
    <div className='space-y-4'>
      <div className='overflow-x-auto'>
        <table className='w-full text-sm'>
          <thead>
            <tr className='border-b text-left'>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Date</th>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Amount</th>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Message</th>
              <th className='pb-2 font-medium text-muted-foreground'>Proof</th>
            </tr>
          </thead>
          <tbody>
            {payments.data.map((payment) => (
              <tr key={payment.id} className='border-b last:border-0'>
                <td className='py-2.5 pr-4 text-muted-foreground whitespace-nowrap'>
                  {formatDate(payment.paid_at)}
                </td>
                <td className='py-2.5 pr-4 font-semibold text-green-600 dark:text-green-400 whitespace-nowrap'>
                  SAR {parseFloat(payment.amount).toLocaleString()}
                </td>
                <td className='py-2.5 pr-4 text-muted-foreground max-w-[240px] truncate'>
                  {payment.message || <span className='italic'>—</span>}
                </td>
                <td className='py-2.5'>
                  {payment.proof_url
                    ? <ProofDialog url={payment.proof_url} />
                    : <span className='text-muted-foreground'>—</span>
                  }
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {payments.last_page > 1 && (
        <div className='flex items-center justify-between pt-2'>
          <span className='text-xs text-muted-foreground'>
            Showing {payments.from}–{payments.to} of {payments.total}
          </span>
          <div className='flex items-center gap-1'>
            <Button
              variant='outline'
              size='icon'
              className='h-7 w-7'
              disabled={payments.current_page === 1}
              onClick={() => onPageChange(payments.current_page - 1)}
            >
              <ChevronLeft className='h-4 w-4' />
            </Button>
            <span className='px-2 text-xs'>
              {payments.current_page} / {payments.last_page}
            </span>
            <Button
              variant='outline'
              size='icon'
              className='h-7 w-7'
              disabled={payments.current_page === payments.last_page}
              onClick={() => onPageChange(payments.current_page + 1)}
            >
              <ChevronRight className='h-4 w-4' />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
