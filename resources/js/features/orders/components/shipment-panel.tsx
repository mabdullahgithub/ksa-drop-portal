import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
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
import { Truck, RefreshCw, X, Copy, ExternalLink, MapPin, AlertTriangle, CheckCircle2, ShieldCheck, Bike, Image as ImageIcon, UserMinus, Undo2, PackageCheck } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'
import { useState } from 'react'
import { BUSINESS_TIMEZONE } from '@/lib/business-time'
import { EmptyState } from '@/components/empty-state'

interface TrackingEvent {
  status: string
  description: string
  location: string | null
  timestamp: string
  raw_status: string | null
}

/** A KSA Express update by a rider, or by staff from the portal (shipment_events). */
interface RiderEvent {
  id: number
  action: 'out_for_delivery' | 'delivered' | 'attempt_failed' | 'returned' | 'cancelled' | 'returned_to_hub'
  reason: string | null
  note: string | null
  cod_amount: string | null
  payment_method: string | null
  recipient_name: string | null
  lat: string | null
  lng: string | null
  entry_method: 'camera' | 'manual' | null
  occurred_at: string
  has_photo: boolean
  rider: { id: number; name: string } | null
  user?: { id: number; name: string } | null
}

interface Shipment {
  id: number
  courier: string
  rider_id?: number | null
  rider?: { id: number; name: string; phone: string } | null
  events?: RiderEvent[]
  tracking_number: string | null
  txlogistic_id: string
  sorting_code: string | null
  status: string
  status_label: string
  status_color: string
  courier_status: string | null
  courier_status_description: string | null
  tracking_history: TrackingEvent[] | null
  shipped_at: string | null
  delivered_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  /** A returned or cancelled parcel is with its rider until the hub has it back. */
  hub_received_at?: string | null
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

const courierLabels: Record<string, string> = {
  jnt_express: 'J&T Express',
  imile: 'iMile',
  logestechs: 'LogesTechs',
  ksadrop_express: 'KSA Express',
}

const courierLabel = (courier: string) => courierLabels[courier] || courier

/**
 * Couriers send event times in different shapes: J&T "2026-09-27 14:05:00"
 * (already local), while KSA Express and LogesTechs send ISO 8601 with an
 * offset. Show the ISO ones in business time; leave the rest as sent.
 */
const formatEventTime = (timestamp: string) =>
  /^\d{4}-\d{2}-\d{2}T/.test(timestamp) && !Number.isNaN(Date.parse(timestamp))
    ? new Date(timestamp).toLocaleString(undefined, { timeZone: BUSINESS_TIMEZONE })
    : timestamp

const riderActionLabels: Record<RiderEvent['action'], string> = {
  out_for_delivery: 'Out for delivery',
  delivered: 'Delivered',
  attempt_failed: 'Delivery failed',
  returned: 'Marked returned',
  cancelled: 'Marked cancelled',
  returned_to_hub: 'Handed back at the hub',
}

const failedReasonLabels: Record<string, string> = {
  no_answer: 'Customer not answering',
  refused: 'Customer refused',
  wrong_address: 'Wrong address',
  reschedule: 'Customer asked for another day',
  no_cash: 'Customer had no cash',
  closed: 'Place closed',
  other: 'Other',
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
  const [unassigning, setUnassigning] = useState(false)
  const [returning, setReturning] = useState(false)
  const [receiving, setReceiving] = useState(false)

  if (!shipment) {
    return (
      <EmptyState
        bot='pill'
        title='No shipment created yet'
        action={
          <Button onClick={onCreateShipment} size='sm'>
            Create Shipment
          </Button>
        }
        className='py-6'
      />
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

  const unassignRider = async () => {
    if (!confirm(`Take this parcel off ${shipment.rider?.name ?? 'the rider'}? Any rider can then scan it out.`)) return

    setUnassigning(true)
    try {
      await axios.post(`/api/shipments/${shipment.id}/unassign-rider`)
      toast.success('Rider unassigned')
      onShipmentUpdated?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to unassign rider')
    } finally {
      setUnassigning(false)
    }
  }

  const markReturned = async () => {
    const reason = prompt('Why is this parcel being returned?')
    if (!reason) return

    setReturning(true)
    try {
      const { data } = await axios.post(`/api/shipments/${shipment.id}/return`, { reason })
      toast.success(data.message)
      onShipmentUpdated?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to mark the shipment returned')
    } finally {
      setReturning(false)
    }
  }

  const receiveAtHub = async () => {
    if (!confirm(`Has the hub got this parcel back from ${shipment.rider?.name ?? 'the rider'}?`)) return

    setReceiving(true)
    try {
      const { data } = await axios.post(`/api/shipments/${shipment.id}/receive-at-hub`)
      toast.success(data.message)
      onShipmentUpdated?.()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to mark the parcel received')
    } finally {
      setReceiving(false)
    }
  }

  const isTerminal = ['delivered', 'cancelled', 'failed', 'returned'].includes(shipment.status)
  const isKsaExpress = shipment.courier === 'ksadrop_express'
  const wentBack = shipment.status === 'cancelled' || shipment.status === 'returned'
  // Returned or cancelled while a rider had it, and not handed back yet.
  const awaitingHandBack = wentBack && !!shipment.rider && !shipment.hub_received_at
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

      {/* Raw courier text — e.g. distinguishes "In Transit" meaning outbound
          from "In Transit" meaning a parcel returning to the warehouse. */}
      {shipment.courier_status_description && (
        <p className='text-xs text-muted-foreground -mt-2'>{shipment.courier_status_description}</p>
      )}

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
          <div className='font-medium'>{courierLabel(shipment.courier)}</div>
        </div>
        <div>
          <div className='text-muted-foreground'>Weight</div>
          <div className='font-medium'>{shipment.weight} kg</div>
        </div>
        {shipment.rider && (
          <div className='col-span-2'>
            <div className='text-muted-foreground'>Rider</div>
            <div className='font-medium flex items-center gap-2'>
              <Bike className='h-3 w-3' />
              <span>{shipment.rider.name}</span>
              <a href={`tel:${shipment.rider.phone}`} className='font-normal text-muted-foreground hover:text-foreground' dir='ltr'>
                {shipment.rider.phone}
              </a>
              {!isTerminal && (
                <Button size='sm' variant='ghost' className='ms-auto h-6 px-2 text-xs' onClick={unassignRider} disabled={unassigning}>
                  <UserMinus className='h-3 w-3 mr-1' />
                  {unassigning ? 'Unassigning…' : 'Unassign'}
                </Button>
              )}
            </div>
            {awaitingHandBack && (
              <div className='mt-1.5 flex items-center gap-2 rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-300'>
                <Undo2 className='h-3 w-3 shrink-0' />
                <span>Still with the rider — not handed back at the hub yet.</span>
                <Button size='sm' variant='outline' className='ms-auto h-6 px-2 text-xs' onClick={receiveAtHub} disabled={receiving}>
                  <PackageCheck className='h-3 w-3 mr-1' />
                  {receiving ? 'Saving…' : 'Mark received'}
                </Button>
              </div>
            )}
            {wentBack && shipment.hub_received_at && (
              <div className='mt-1 text-xs text-muted-foreground'>
                Back at the hub {new Date(shipment.hub_received_at).toLocaleString(undefined, { timeZone: BUSINESS_TIMEZONE })}
              </div>
            )}
          </div>
        )}
        {isDelivered && shipment.delivered_at && (
          <div>
            <div className='text-muted-foreground'>Delivered</div>
            <div className='font-medium flex items-center gap-1'>
              <CheckCircle2 className='h-3 w-3 text-green-600' />
              {new Date(shipment.delivered_at).toLocaleDateString(undefined, { timeZone: BUSINESS_TIMEZONE })}
              {shipment.otp_verified && shipment.otp_verified_at && (
                <span className='text-xs text-muted-foreground'>(OTP at {new Date(shipment.otp_verified_at).toLocaleDateString(undefined, { timeZone: BUSINESS_TIMEZONE })})</span>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Exception note — only surfaced while the shipment is actually in
          Exception; once it recovers, the backend clears exception_escalated_at
          but keeps exception_note as history, so gate display on current
          status rather than the note's mere presence. */}
      {isException && shipment.exception_note && (
        <div className='rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400'>
          <div className='font-medium flex items-center gap-1 mb-1'>
            <AlertTriangle className='h-3 w-3' /> Exception Note
          </div>
          {shipment.exception_note}
          {shipment.exception_escalated_at && (
            <div className='text-xs mt-1 opacity-70'>
              Escalated on {new Date(shipment.exception_escalated_at).toLocaleString(undefined, { timeZone: BUSINESS_TIMEZONE })}
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
                      {formatEventTime(event.timestamp)}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </>
      )}

      {/* KSA Express rider updates: the full record behind the public
          tracking lines above — cash, photo, location. */}
      {shipment.events && shipment.events.length > 0 && (
        <>
          <Separator />
          <div>
            <div className='text-sm font-medium mb-2'>Rider Updates</div>
            <div className='space-y-2 max-h-56 overflow-y-auto'>
              {shipment.events.map((event) => (
                <div key={event.id} className='rounded-md border p-2 text-sm'>
                  <div className='flex items-center gap-2'>
                    <span className='font-medium'>{riderActionLabels[event.action] ?? event.action}</span>
                    {event.entry_method === 'manual' && (
                      <Badge variant='outline' className='h-5 px-1.5 text-[10px] text-amber-700 border-amber-300' title='The rider typed or picked the parcel instead of scanning its label'>
                        not scanned
                      </Badge>
                    )}
                    <span className='ms-auto text-xs text-muted-foreground'>
                      {new Date(event.occurred_at).toLocaleString(undefined, { timeZone: BUSINESS_TIMEZONE })}
                    </span>
                  </div>
                  <div className='text-xs text-muted-foreground space-y-0.5 mt-1'>
                    {event.rider && <div>By {event.rider.name}</div>}
                    {!event.rider && event.user && <div>By {event.user.name} (staff)</div>}
                    {event.reason && <div>Reason: {failedReasonLabels[event.reason] ?? event.reason}</div>}
                    {event.cod_amount && Number(event.cod_amount) > 0 && (
                      <div>Collected SAR {Number(event.cod_amount).toFixed(2)}{event.payment_method && ` · ${event.payment_method}`}</div>
                    )}
                    {event.recipient_name && <div>Received by {event.recipient_name}</div>}
                    {event.note && <div className='text-foreground'>“{event.note}”</div>}
                  </div>
                  {(event.has_photo || (event.lat && event.lng)) && (
                    <div className='flex gap-3 mt-1.5 text-xs'>
                      {event.has_photo && (
                        <a href={`/api/shipment-events/${event.id}/photo`} target='_blank' rel='noopener noreferrer' className='inline-flex items-center gap-1 text-primary hover:underline'>
                          <ImageIcon className='h-3 w-3' /> Photo
                        </a>
                      )}
                      {event.lat && event.lng && (
                        <a href={`https://www.google.com/maps?q=${event.lat},${event.lng}`} target='_blank' rel='noopener noreferrer' className='inline-flex items-center gap-1 text-primary hover:underline'>
                          <MapPin className='h-3 w-3' /> Location
                        </a>
                      )}
                    </div>
                  )}
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

            {/* Other couriers report their own returns; ours is marked here. */}
            {isKsaExpress && (
              <Button size='sm' variant='outline' onClick={markReturned} disabled={returning}>
                <Undo2 className='h-3 w-3 mr-1' />
                {returning ? 'Saving…' : 'Mark returned'}
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
