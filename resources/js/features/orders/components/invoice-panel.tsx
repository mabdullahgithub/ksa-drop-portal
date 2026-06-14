import { Button } from '@/components/ui/button'
import { FileText, Download, Eye, Receipt, Loader2, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'
import { useState } from 'react'
import { type Order, type Invoice } from '@/types/order'

interface InvoicePanelProps {
  order: Order
  onInvoicesChanged?: () => void
}

export function InvoicePanel({ order, onInvoicesChanged }: InvoicePanelProps) {
  const invoices = order.invoices || []
  const shipping = invoices.find((i) => i.type === 'shipping')
  const hasShipment = !!order.latest_shipment?.tracking_number

  const [generating, setGenerating] = useState<'shipping' | null>(null)
  const [deleting, setDeleting] = useState(false)

  const generateShipping = async () => {
    if (!order.latest_shipment) return
    setGenerating('shipping')
    try {
      await axios.post(`/api/shipments/${order.latest_shipment.id}/invoice`)
      toast.success('Waybill generated')
      onInvoicesChanged?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to generate waybill')
    } finally {
      setGenerating(null)
    }
  }

  const deleteInvoice = async (invoice: Invoice) => {
    if (!window.confirm('Delete this waybill? You can regenerate it later.')) return
    setDeleting(true)
    try {
      await axios.delete(`/api/invoices/${invoice.id}`)
      toast.success('Waybill deleted')
      onInvoicesChanged?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to delete waybill')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className='space-y-3'>
      <div className='flex items-center gap-2'>
        <Receipt className='h-4 w-4' />
        <span className='font-semibold'>Documents</span>
      </div>

      <InvoiceRow
        icon={<FileText className='h-4 w-4 text-muted-foreground' />}
        title='Shipping Waybill'
        invoice={shipping}
        generating={generating === 'shipping'}
        onGenerate={generateShipping}
        onDelete={deleteInvoice}
        deleting={deleting}
        generateLabel='Generate'
        disabled={!hasShipment}
        disabledHint='Create a shipment first'
      />
    </div>
  )
}

interface InvoiceRowProps {
  icon: React.ReactNode
  title: string
  invoice?: Invoice
  generating: boolean
  onGenerate: () => void
  onDelete?: (invoice: Invoice) => void
  deleting?: boolean
  generateLabel: string
  disabled?: boolean
  disabledHint?: string
}

function InvoiceRow({ icon, title, invoice, generating, onGenerate, onDelete, deleting, generateLabel, disabled, disabledHint }: InvoiceRowProps) {
  return (
    <div className='flex items-center justify-between rounded-lg border p-3 text-sm'>
      <div className='flex items-center gap-2'>
        {icon}
        <div>
          <div className='font-medium'>{title}</div>
          {invoice ? (
            <div className='text-xs text-muted-foreground font-mono'>{invoice.invoice_number}</div>
          ) : (
            <div className='text-xs text-muted-foreground'>
              {disabled ? disabledHint : 'Not generated'}
            </div>
          )}
        </div>
      </div>
      <div className='flex gap-2'>
        {invoice ? (
          <>
            <Button
              size='sm'
              variant='outline'
              onClick={() => window.open(`/api/invoices/${invoice.id}/preview`, '_blank')}
            >
              <Eye className='h-3 w-3 mr-1' />
              View
            </Button>
            <Button
              size='sm'
              variant='outline'
              onClick={() => window.open(`/api/invoices/${invoice.id}/download`, '_blank')}
            >
              <Download className='h-3 w-3 mr-1' />
              PDF
            </Button>
            {onDelete && (
              <Button
                size='sm'
                variant='outline'
                className='text-destructive hover:text-destructive'
                onClick={() => onDelete(invoice)}
                disabled={deleting}
                title='Delete waybill'
              >
                {deleting ? <Loader2 className='h-3 w-3 animate-spin' /> : <Trash2 className='h-3 w-3' />}
              </Button>
            )}
          </>
        ) : (
          <Button size='sm' variant='outline' onClick={onGenerate} disabled={disabled || generating}>
            {generating ? <Loader2 className='h-3 w-3 mr-1 animate-spin' /> : null}
            {generateLabel}
          </Button>
        )}
      </div>
    </div>
  )
}
