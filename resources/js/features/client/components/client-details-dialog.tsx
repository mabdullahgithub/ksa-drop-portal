import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Building2, MapPin, FileText, DollarSign, Receipt } from 'lucide-react'
import type { Client } from '@/types/client'

interface ClientDetailsDialogProps {
  client: Client | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ClientDetailsDialog({ client, open, onOpenChange }: ClientDetailsDialogProps) {
  if (!client) return null

  const statusColorMap = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    inactive: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    suspended: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-3xl max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle className='text-2xl flex items-center gap-2'>
            <code className='rounded bg-muted px-2 py-0.5 text-lg'>{client.client_id}</code>
            {client.company_name}
          </DialogTitle>
        </DialogHeader>

        <div className='space-y-6'>
          {/* Status & Type */}
          <div className='flex flex-wrap gap-4'>
            <div>
              <div className='text-sm font-medium text-muted-foreground mb-1'>Status</div>
              <Badge variant='outline' className={`text-xs capitalize ${statusColorMap[client.status]}`}>
                {client.status}
              </Badge>
            </div>
            <div>
              <div className='text-sm font-medium text-muted-foreground mb-1'>Type</div>
              <div className='flex gap-1'>
                {client.client_types.map((type) => (
                  <Badge key={type} variant='outline' className='capitalize'>
                    {type}
                  </Badge>
                ))}
              </div>
            </div>
            <div>
              <div className='text-sm font-medium text-muted-foreground mb-1'>Portal Access</div>
              <div className='flex gap-1'>
                {(client.portal_features || []).map((feature) => (
                  <Badge key={feature} variant='outline' className='text-xs capitalize'>
                    {feature}
                  </Badge>
                ))}
              </div>
            </div>
          </div>

          <Separator />

          {/* Contact Info */}
          <div>
            <h3 className='font-semibold mb-3 flex items-center gap-2'>
              <Building2 className='h-4 w-4' />
              Contact Information
            </h3>
            <div className='grid grid-cols-2 gap-4 text-sm'>
              <InfoRow label='Contact Person' value={client.contact_person} />
              <InfoRow label='Email' value={client.user?.email} />
              <InfoRow label='Phone' value={client.phone} />
              <InfoRow label='Secondary Phone' value={client.secondary_phone} />
            </div>
          </div>

          <Separator />

          {/* Address */}
          <div>
            <h3 className='font-semibold mb-3 flex items-center gap-2'>
              <MapPin className='h-4 w-4' />
              Address
            </h3>
            <div className='grid grid-cols-2 gap-4 text-sm'>
              <InfoRow label='Address' value={client.address} />
              <InfoRow label='City' value={client.city} />
              <InfoRow label='Country' value={client.country} />
              <InfoRow label='Postal Code' value={client.postal_code} />
            </div>
          </div>

          <Separator />

          {/* Business Details */}
          <div>
            <h3 className='font-semibold mb-3 flex items-center gap-2'>
              <FileText className='h-4 w-4' />
              Business Details
            </h3>
            <div className='grid grid-cols-2 gap-4 text-sm'>
              <InfoRow label='Tax ID (VAT)' value={client.tax_id} />
              <InfoRow label='Commercial Registration' value={client.commercial_registration} />
            </div>
          </div>

          {/* Charges */}
          {client.charges && Object.values(client.charges).some((v) => v != null) && (
            <>
              <Separator />
              <div>
                <h3 className='font-semibold mb-3 flex items-center gap-2'>
                  <Receipt className='h-4 w-4' />
                  Client Charges
                </h3>
                <div className='grid grid-cols-2 gap-4 text-sm'>
                  <InfoRow label='Delivery Charges' value={client.charges.delivery != null ? `SAR ${client.charges.delivery}` : null} />
                  <InfoRow label='Return Charges' value={client.charges.return != null ? `SAR ${client.charges.return}` : null} />
                  <InfoRow label='COD Charges' value={client.charges.cod != null ? `SAR ${client.charges.cod}` : null} />
                  <InfoRow label='Warehousing Charges' value={client.charges.warehousing != null ? `SAR ${client.charges.warehousing}` : null} />
                  <InfoRow label='Call Confirmation Charges' value={client.charges.call_confirmation != null ? `SAR ${client.charges.call_confirmation}` : null} />
                  <InfoRow label='VAT' value={client.charges.vat != null ? `${client.charges.vat}%` : null} />
                  <InfoRow label='Other Charges' value={client.charges.other != null ? `SAR ${client.charges.other}` : null} />
                </div>
              </div>
            </>
          )}

          {/* Stats */}
          {(client.orders_count !== undefined || client.total_revenue !== undefined) && (
            <>
              <Separator />
              <div>
                <h3 className='font-semibold mb-3 flex items-center gap-2'>
                  <DollarSign className='h-4 w-4' />
                  Statistics
                </h3>
                <div className='grid grid-cols-3 gap-4 text-sm'>
                  <div className='rounded border p-3 text-center'>
                    <div className='text-2xl font-bold'>{client.orders_count ?? 0}</div>
                    <div className='text-xs text-muted-foreground'>Total Orders</div>
                  </div>
                  <div className='rounded border p-3 text-center'>
                    <div className='text-2xl font-bold'>SAR {(client.total_revenue ?? 0).toLocaleString()}</div>
                    <div className='text-xs text-muted-foreground'>Total Revenue</div>
                  </div>
                  <div className='rounded border p-3 text-center'>
                    <div className='text-2xl font-bold'>{client.products_count ?? 0}</div>
                    <div className='text-xs text-muted-foreground'>Products</div>
                  </div>
                </div>
              </div>
            </>
          )}

          {client.notes && (
            <>
              <Separator />
              <div>
                <div className='text-sm font-medium text-muted-foreground'>Notes</div>
                <p className='text-sm mt-1'>{client.notes}</p>
              </div>
            </>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}

function InfoRow({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <div className='text-muted-foreground'>{label}</div>
      <div className='font-medium'>{value || '—'}</div>
    </div>
  )
}
