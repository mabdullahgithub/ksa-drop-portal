import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Truck, RefreshCw, X, Copy, ExternalLink, MapPin } from 'lucide-react'
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
  weight: string
  service_type: string
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

  const isTerminal = ['delivered', 'cancelled', 'failed', 'returned'].includes(shipment.status)

  return (
    <div className='space-y-4'>
      <div className='flex items-center justify-between'>
        <div className='flex items-center gap-2'>
          <Truck className='h-4 w-4' />
          <span className='font-semibold'>Shipment</span>
        </div>
        <Badge variant='outline' className={statusColorMap[shipment.status_color] || statusColorMap.gray}>
          {shipment.status_label}
        </Badge>
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
      </div>

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
                    <div className={`h-2 w-2 rounded-full ${index === 0 ? 'bg-primary' : 'bg-muted-foreground/30'}`} />
                    {index < shipment.tracking_history!.length - 1 && (
                      <div className='w-px flex-1 bg-muted-foreground/20 mt-1' />
                    )}
                  </div>
                  <div className='flex-1 pb-3'>
                    <div className='font-medium'>{event.description}</div>
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
            Track Shipment
          </a>
        </Button>
        {!isTerminal && (
          <>
            <Button size='sm' variant='outline' onClick={refreshTracking} disabled={refreshing}>
              <RefreshCw className={`h-3 w-3 mr-1 ${refreshing ? 'animate-spin' : ''}`} />
              {refreshing ? 'Refreshing...' : 'Refresh'}
            </Button>
            <Button size='sm' variant='destructive' onClick={cancelShipment} disabled={cancelling}>
              <X className='h-3 w-3 mr-1' />
              {cancelling ? 'Cancelling...' : 'Cancel'}
            </Button>
          </>
        )}
      </div>
    </div>
  )
}
