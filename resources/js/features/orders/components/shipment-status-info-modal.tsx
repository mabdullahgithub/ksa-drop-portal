import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
  Package,
  Truck,
  MapPin,
  CheckCircle2,
  AlertCircle,
  RotateCcw,
  XCircle,
} from 'lucide-react'

const statusDescriptions = [
  {
    status: 'Pending',
    icon: Package,
    color: 'text-slate-600 dark:text-slate-400',
    description: 'Order has been placed and is awaiting shipment preparation.',
  },
  {
    status: 'Info Received',
    icon: Package,
    color: 'text-blue-600 dark:text-blue-400',
    description: 'Shipment information has been received by J&T Express.',
  },
  {
    status: 'In Transit',
    icon: Truck,
    color: 'text-indigo-600 dark:text-indigo-400',
    description: 'Package is in transit and moving towards its destination.',
  },
  {
    status: 'Out for Delivery',
    icon: MapPin,
    color: 'text-orange-600 dark:text-orange-400',
    description: 'Package is out for delivery and will arrive today.',
  },
  {
    status: 'Delivered',
    icon: CheckCircle2,
    color: 'text-green-600 dark:text-green-400',
    description: 'Package has been successfully delivered to the recipient.',
  },
  {
    status: 'Attempt Failed',
    icon: AlertCircle,
    color: 'text-red-600 dark:text-red-400',
    description: 'Delivery attempt failed. Will be retried or customer will be contacted.',
  },
  {
    status: 'Returned',
    icon: RotateCcw,
    color: 'text-yellow-600 dark:text-yellow-400',
    description: 'Package has been returned to sender or warehouse.',
  },
  {
    status: 'Exception',
    icon: AlertCircle,
    color: 'text-red-600 dark:text-red-400',
    description: 'There is an issue with the shipment (lost, damaged, or other problem).',
  },
  {
    status: 'Cancelled',
    icon: XCircle,
    color: 'text-gray-600 dark:text-gray-400',
    description: 'Shipment has been cancelled and will not be delivered.',
  },
  {
    status: 'Failed',
    icon: XCircle,
    color: 'text-red-600 dark:text-red-400',
    description: 'Shipment failed to deliver and cannot be processed further.',
  },
]

interface ShipmentStatusInfoModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ShipmentStatusInfoModal({ open, onOpenChange }: ShipmentStatusInfoModalProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-2xl max-h-[80vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>J&T Shipment Status Definitions</DialogTitle>
        </DialogHeader>

        <div className='space-y-4'>
          {statusDescriptions.map((item) => {
            const Icon = item.icon
            return (
              <div key={item.status} className='flex gap-4 pb-4 border-b last:border-0'>
                <div className='shrink-0 flex items-start pt-1'>
                  <div className={`${item.color}`}>
                    <Icon className='h-5 w-5' />
                  </div>
                </div>
                <div className='flex-1 min-w-0'>
                  <h4 className='font-semibold text-sm mb-1'>{item.status}</h4>
                  <p className='text-sm text-muted-foreground'>{item.description}</p>
                </div>
              </div>
            )
          })}
        </div>
      </DialogContent>
    </Dialog>
  )
}
