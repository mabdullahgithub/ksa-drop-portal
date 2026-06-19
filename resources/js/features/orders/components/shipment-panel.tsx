import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Truck, RefreshCw, X, Copy, ExternalLink, MapPin, AlertTriangle, CheckCircle2, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'
import { useState } from 'react'

interface TrackingEvent {
  status: string
  description: string
  location: string | null
  timestamp: string
  raw_status: string | null
}

interface Shipment {
  id: number
  courier: string
  tracking_number: string | null
  txlogistic_id: string
  sorting_code: string | null
  status: string
  status_label: string
  status_color: string
  tracking_history: TrackingEvent[] | null
  shipped_at: string | null
  delivered_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  error_message: string | null
  exception_note: string | null
  exception_escalated_at: string | null
  otp_verified: boolean
  otp_verified_at: string | null
  weight: string
  service_type: string
  label_url: string | null
}

interface ShipmentPanelProps {
  shipment: Shipment | null
  orderId: number
  onCreateShipment: () => void
  onShipmentUpdated?: () => void
}

const statusColorMap: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
  blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
  orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
  red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
}

export function ShipmentPanel({ shipment, orderId, onCreateShipment, onShipmentUpdated }: ShipmentPanelProps) {
  const [refreshing, setRefreshing] = useState(false)
  const [cancelling, setCancelling] = useState(false)
  const [escalating, setEscalating] = useState(false)
  const [showEscalateDialog, setShowEscalateDialog] = useState(false)
  const [escalateNote, setEscalateNote] = useState('')

  if (!shipment) {
    return (
      <div className='flex flex-col items-center justify-center py-6 text-center'>
        <Truck className='h-10 w-10 text-muted-foreground mb-3' />
        <p className='text-sm text-muted-foreground mb-3'>No shipment created yet</p>
        <Button onClick={onCreateShipment} size='sm'>
          Create Shipment
        </Button>
      </div>
    )
  }

  const copyTrackingNumber = () => {
    if (shipment.tracking_number) {
      navigator.clipboard.writeText(shipment.tracking_number)
      toast.success('Tracking number copied!')
    }
  }

  const refreshTracking = async () => {
    setRefreshing(true)
    try {
      await axios.post(`/api/shipments/${shipment.id}/track`)
      toast.success('Tracking updated!')
      onShipmentUpdated?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to refresh tracking')
    } finally {
      setRefreshing(false)
    }
  }

  const cancelShipment = async () => {
    const reason = prompt('Please enter a reason for cancellation:')
    if (!reason) return

    setCancelling(true)
    try {
      await axios.post(`/api/shipments/${shipment.id}/cancel`, { reason })
      toast.success('Shipment cancelled!')
      onShipmentUpdated?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to cancel shipment')
    } finally {
      setCancelling(false)
    }
  }

  const escalateException = async () => {
    setEscalating(true)
    try {
      await axios.post(`/api/shipments/${shipment.id}/escalate`, { note: escalateNote })
      toast.success('Exception escalated — admins notified')
      setShowEscalateDialog(false)
      setEscalateNote('')
      onShipmentUpdated?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to escalate')
    } finally {
      setEscalating(false)
    }
  }

  const isTerminal = ['delivered', 'cancelled', 'failed', 'returned'].includes(shipment.status)
  const isException = shipment.status === 'exception'
  const isDelivered = shipment.status === 'delivered'

  return (
    <div className='space-y-4'>
      <div className='flex items-center justify-between'>
        <div className='flex items-center gap-2'>
          <Truck className='h-4 w-4' />
          <span className='font-semibold'>Shipment</span>
        </div>
        <div className='flex items-center gap-2'>
          {shipment.otp_verified && (
            <Badge variant='outline' className='bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 gap-1'>
              <ShieldCheck className='h-3 w-3' />
              OTP Verified
            </Badge>
          )}
          {isException && (
            <Badge variant='outline' className='bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 gap-1'>
              <AlertTriangle className='h-3 w-3' />
              Exception
            </Badge>
          )}
          <Badge variant='outline' className={statusColorMap[shipment.status_color] || statusColorMap.gray}>
            {shipment.status_label}
          </Badge>
        </div>
      </div>

      <div className='grid grid-cols-2 gap-3 text-sm'>
        {shipment.tracking_number && (
          <div>
            <div className='text-muted-foreground'>Tracking Number</div>
            <div className='font-medium flex items-center gap-1'>
              <span className='font-mono'>{shipment.tracking_number}</span>
              <button onClick={copyTrackingNumber} className='text-muted-foreground hover:text-foreground'>
                <Copy className='h-3 w-3' />
              </button>
              <a
                href='/track'
                target='_blank'
                rel='noopener noreferrer'
                className='text-muted-foreground hover:text-foreground'
              >
                <ExternalLink className='h-3 w-3' />
              </a>
            </div>
          </div>
        )}
        {shipment.sorting_code && (
          <div>
            <div className='text-muted-foreground'>Sorting Code</div>
            <div className='font-medium font-mono'>{shipment.sorting_code}</div>
          </div>
        )}
        <div>
          <div className='text-muted-foreground'>Courier</div>
          <div className='font-medium'>J&T Express</div>
        </div>
        <div>
          <div className='text-muted-foreground'>Weight</div>
          <div className='font-medium'>{shipment.weight} kg</div>
        </div>
        {isDelivered && shipment.delivered_at && (
          <div>
            <div className='text-muted-foreground'>Delivered</div>
            <div className='font-medium flex items-center gap-1'>
              <CheckCircle2 className='h-3 w-3 text-green-600' />
              {new Date(shipment.delivered_at).toLocaleDateString()}
              {shipment.otp_verified && shipment.otp_verified_at && (
                <span className='text-xs text-muted-foreground'>(OTP at {new Date(shipment.otp_verified_at).toLocaleDateString()})</span>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Exception note */}
      {shipment.exception_note && (
        <div className='rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400'>
          <div className='font-medium flex items-center gap-1 mb-1'>
            <AlertTriangle className='h-3 w-3' /> Exception Note
          </div>
          {shipment.exception_note}
          {shipment.exception_escalated_at && (
            <div className='text-xs mt-1 opacity-70'>
              Escalated on {new Date(shipment.exception_escalated_at).toLocaleString()}
            </div>
          )}
        </div>
      )}

      {/* Tracking Timeline */}
      {shipment.tracking_history && shipment.tracking_history.length > 0 && (
        <>
          <Separator />
          <div>
            <div className='text-sm font-medium mb-2'>Tracking History</div>
            <div className='space-y-3 max-h-48 overflow-y-auto'>
              {shipment.tracking_history.map((event, index) => (
                <div key={index} className='flex gap-3 text-sm'>
                  <div className='flex flex-col items-center'>
                    <div className={`h-2 w-2 rounded-full ${
                      index === 0
                        ? event.raw_status === 'OTP_VERIFIED'
                          ? 'bg-emerald-500'
                          : 'bg-primary'
                        : 'bg-muted-foreground/30'
                    }`} />
                    {index < shipment.tracking_history!.length - 1 && (
                      <div className='w-px flex-1 bg-muted-foreground/20 mt-1' />
                    )}
                  </div>
                  <div className='flex-1 pb-3'>
                    <div className='font-medium flex items-center gap-1'>
                      {event.description}
                      {event.raw_status === 'OTP_VERIFIED' && (
                        <ShieldCheck className='h-3 w-3 text-emerald-500' />
                      )}
                    </div>
                    <div className='text-muted-foreground text-xs'>
                      {event.location && `${event.location} · `}
                      {event.timestamp}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </>
      )}

      {/* Error Message */}
      {shipment.error_message && (
        <div className='rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400'>
          {shipment.error_message}
        </div>
      )}

      {/* Actions */}
      <Separator />
      <div className='flex flex-wrap gap-2'>
        <Button size='sm' variant='outline' asChild>
          <a href='/track' target='_blank' rel='noopener noreferrer'>
            <MapPin className='h-3 w-3 mr-1' />
            Track
          </a>
        </Button>

        {!isTerminal && (
          <>
            <Button size='sm' variant='outline' onClick={refreshTracking} disabled={refreshing}>
              <RefreshCw className={`h-3 w-3 mr-1 ${refreshing ? 'animate-spin' : ''}`} />
              {refreshing ? 'Refreshing...' : 'Refresh'}
            </Button>

            {isException && !shipment.exception_escalated_at && (
              <Button size='sm' variant='outline' className='text-orange-600 border-orange-300' onClick={() => setShowEscalateDialog(true)}>
                <AlertTriangle className='h-3 w-3 mr-1' />
                Escalate
              </Button>
            )}

            <Button size='sm' variant='destructive' onClick={cancelShipment} disabled={cancelling}>
              <X className='h-3 w-3 mr-1' />
              {cancelling ? 'Cancelling...' : 'Cancel'}
            </Button>
          </>
        )}
      </div>

      {/* Escalate Exception Dialog */}
      <Dialog open={showEscalateDialog} onOpenChange={setShowEscalateDialog}>
        <DialogContent className='sm:max-w-md'>
          <DialogHeader>
            <DialogTitle>Escalate Exception</DialogTitle>
            <DialogDescription>Add a note and notify admins about this shipment exception.</DialogDescription>
          </DialogHeader>
          <div className='py-2'>
            <Label className='text-xs'>Note (optional)</Label>
            <Textarea
              className='mt-1'
              rows={3}
              placeholder='Describe the issue...'
              value={escalateNote}
              onChange={e => setEscalateNote(e.target.value)}
            />
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setShowEscalateDialog(false)}>Cancel</Button>
            <Button variant='destructive' onClick={escalateException} disabled={escalating}>
              {escalating ? 'Escalating...' : 'Escalate & Notify Admins'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
