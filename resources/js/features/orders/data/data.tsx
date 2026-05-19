import {
  ArrowDown,
  ArrowRight,
  ArrowUp,
  Circle,
  CheckCircle,
  AlertCircle,
  Timer,
  Truck,
  CircleOff,
  Package,
  RotateCcw,
} from 'lucide-react'

export const labels = [
  {
    value: 'wholesale',
    label: 'Wholesale',
  },
  {
    value: 'retail',
    label: 'Retail',
  },
]

export const statuses = [
  {
    label: 'Pending',
    value: 'pending' as const,
    icon: Circle,
  },
  {
    label: 'Processing',
    value: 'processing' as const,
    icon: Timer,
  },
  {
    label: 'Shipped',
    value: 'shipped' as const,
    icon: Truck,
  },
  {
    label: 'Delivered',
    value: 'delivered' as const,
    icon: CheckCircle,
  },
  {
    label: 'Canceled',
    value: 'canceled' as const,
    icon: CircleOff,
  },
  {
    label: 'Refunded',
    value: 'refunded' as const,
    icon: RotateCcw,
  },
]

export const priorities = [
  {
    label: 'Low',
    value: 'low' as const,
    icon: ArrowDown,
  },
  {
    label: 'Medium',
    value: 'medium' as const,
    icon: ArrowRight,
  },
  {
    label: 'High',
    value: 'high' as const,
    icon: ArrowUp,
  },
  {
    label: 'Urgent',
    value: 'urgent' as const,
    icon: AlertCircle,
  },
]
