import { CheckCircle, Clock, XCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { SignupRequest } from '@/hooks/useInventoryRequests'

export const SIGNUP_SERVICES: Record<string, string> = {
  dropshipper: 'Dropshipping',
  fulfilment: 'Fulfilment',
}

const STATUS_STYLE = {
  pending: {
    label: 'Pending',
    icon: Clock,
    className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  },
  approved: {
    label: 'Approved',
    icon: CheckCircle,
    className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  },
  rejected: {
    label: 'Rejected',
    icon: XCircle,
    className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  },
}

export function SignupStatusBadge({ status }: { status: SignupRequest['status'] }) {
  const { label, icon: Icon, className } = STATUS_STYLE[status]

  return (
    <Badge variant='outline' className={`text-xs ${className}`}>
      <Icon className='mr-1 h-3 w-3' />
      {label}
    </Badge>
  )
}
