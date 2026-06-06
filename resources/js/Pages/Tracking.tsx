import { Head } from '@inertiajs/react'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Truck, Package, CheckCircle, AlertCircle } from 'lucide-react'

interface TrackingEvent {
  status: string
  description: string
  location: string | null
  timestamp: string
}

interface ShipmentData {
  tracking_number: string
  courier: string
  status: string
  status_label: string
  status_color: string
  tracking_history: TrackingEvent[]
  shipped_at: string | null
  delivered_at: string | null
}

interface TrackingProps {
  shipment: ShipmentData | null
  error?: string
}

const statusColorMap: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-800',
  blue: 'bg-blue-100 text-blue-800',
  indigo: 'bg-indigo-100 text-indigo-800',
  orange: 'bg-orange-100 text-orange-800',
  red: 'bg-red-100 text-red-800',
  green: 'bg-green-100 text-green-800',
  yellow: 'bg-yellow-100 text-yellow-800',
}

const statusIcon = (status: string) => {
  if (status === 'delivered') return <CheckCircle className='h-5 w-5 text-green-600' />
  if (['failed', 'exception', 'attempt_fail'].includes(status)) return <AlertCircle className='h-5 w-5 text-red-600' />
  return <Truck className='h-5 w-5 text-blue-600' />
}

export default function Tracking({ shipment, error }: TrackingProps) {
  return (
    <>
      <Head title={shipment ? `Track ${shipment.tracking_number}` : 'Track Shipment'} />

      <div className='min-h-screen bg-gray-50 py-12 px-4'>
        <div className='mx-auto max-w-lg'>
          {/* Header */}
          <div className='text-center mb-8'>
            <div className='flex items-center justify-center gap-2 mb-2'>
              <Package className='h-6 w-6 text-primary' />
              <h1 className='text-xl font-bold'>KSA Drop</h1>
            </div>
            <p className='text-sm text-gray-500'>Shipment Tracking</p>
          </div>

          {error || !shipment ? (
            <Card>
              <CardContent className='py-12 text-center'>
                <AlertCircle className='h-12 w-12 text-gray-400 mx-auto mb-4' />
                <h2 className='text-lg font-semibold text-gray-700'>Shipment Not Found</h2>
                <p className='text-sm text-gray-500 mt-2'>
                  The tracking number you provided could not be found. Please check and try again.
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className='space-y-4'>
              {/* Status Card */}
              <Card>
                <CardHeader className='pb-3'>
                  <div className='flex items-center justify-between'>
                    <CardTitle className='text-base'>Tracking Details</CardTitle>
                    {statusIcon(shipment.status)}
                  </div>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <div className='flex items-center justify-between'>
                    <span className='text-sm text-gray-500'>Status</span>
                    <Badge className={statusColorMap[shipment.status_color] || statusColorMap.gray}>
                      {shipment.status_label}
                    </Badge>
                  </div>
                  <div className='flex items-center justify-between'>
                    <span className='text-sm text-gray-500'>Tracking Number</span>
                    <span className='text-sm font-mono font-medium'>{shipment.tracking_number}</span>
                  </div>
                  <div className='flex items-center justify-between'>
                    <span className='text-sm text-gray-500'>Courier</span>
                    <span className='text-sm font-medium'>J&T Express</span>
                  </div>
                  {shipment.shipped_at && (
                    <div className='flex items-center justify-between'>
                      <span className='text-sm text-gray-500'>Shipped</span>
                      <span className='text-sm'>{new Date(shipment.shipped_at).toLocaleDateString()}</span>
                    </div>
                  )}
                  {shipment.delivered_at && (
                    <div className='flex items-center justify-between'>
                      <span className='text-sm text-gray-500'>Delivered</span>
                      <span className='text-sm'>{new Date(shipment.delivered_at).toLocaleDateString()}</span>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Timeline Card */}
              {shipment.tracking_history && shipment.tracking_history.length > 0 && (
                <Card>
                  <CardHeader className='pb-3'>
                    <CardTitle className='text-base'>Tracking Timeline</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className='space-y-4'>
                      {shipment.tracking_history.map((event, index) => (
                        <div key={index} className='flex gap-3'>
                          <div className='flex flex-col items-center'>
                            <div className={`h-3 w-3 rounded-full ${index === 0 ? 'bg-blue-600' : 'bg-gray-300'}`} />
                            {index < shipment.tracking_history.length - 1 && (
                              <div className='w-px flex-1 bg-gray-200 mt-1' />
                            )}
                          </div>
                          <div className='flex-1 pb-4'>
                            <p className='text-sm font-medium text-gray-900'>{event.description}</p>
                            <p className='text-xs text-gray-500 mt-0.5'>
                              {event.location && `${event.location} · `}
                              {event.timestamp}
                            </p>
                          </div>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}
            </div>
          )}
        </div>
      </div>
    </>
  )
}
