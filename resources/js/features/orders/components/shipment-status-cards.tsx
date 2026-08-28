import { useOrderStatistics } from '@/hooks/useOrders'
import {
  Package,
  Truck,
  MapPin,
  CheckCircle2,
  AlertCircle,
  RotateCcw,
  XCircle,
} from 'lucide-react'

// Ordered by logical process flow
const statusConfig = [
  { key: 'pending', label: 'Pending', icon: Package, color: 'text-slate-600 dark:text-slate-400', gradient: 'from-slate-100 dark:from-slate-900/30', order: 1 },
  { key: 'info_received', label: 'Info Received', icon: Package, color: 'text-blue-600 dark:text-blue-400', gradient: 'from-blue-100 dark:from-blue-900/30', order: 2 },
  { key: 'in_transit', label: 'In Transit', icon: Truck, color: 'text-indigo-600 dark:text-indigo-400', gradient: 'from-indigo-100 dark:from-indigo-900/30', order: 3 },
  { key: 'out_for_delivery', label: 'Out for Delivery', icon: MapPin, color: 'text-orange-600 dark:text-orange-400', gradient: 'from-orange-100 dark:from-orange-900/30', order: 4 },
  { key: 'delivered', label: 'Delivered', icon: CheckCircle2, color: 'text-green-600 dark:text-green-400', gradient: 'from-green-100 dark:from-green-900/30', order: 5 },
  { key: 'attempt_fail', label: 'Attempt Failed', icon: AlertCircle, color: 'text-red-600 dark:text-red-400', gradient: 'from-red-100 dark:from-red-900/30', order: 6 },
  { key: 'returned', label: 'Returned', icon: RotateCcw, color: 'text-yellow-600 dark:text-yellow-400', gradient: 'from-yellow-100 dark:from-yellow-900/30', order: 7 },
  { key: 'exception', label: 'Exception', icon: AlertCircle, color: 'text-red-600 dark:text-red-400', gradient: 'from-red-100 dark:from-red-900/30', order: 8 },
  { key: 'cancelled', label: 'Cancelled', icon: XCircle, color: 'text-gray-600 dark:text-gray-400', gradient: 'from-gray-100 dark:from-gray-900/30', order: 9 },
  { key: 'failed', label: 'Failed', icon: XCircle, color: 'text-red-600 dark:text-red-400', gradient: 'from-red-100 dark:from-red-900/30', order: 10 },
]

function ShipmentStatusCardSkeleton() {
  return (
    <div className='flex flex-col justify-between rounded-lg border border-muted/60 bg-card p-3 shadow-sm'>
      <div className='mb-2 h-3 w-16 rounded bg-muted animate-pulse' />
      <div className='h-5 w-8 rounded bg-muted animate-pulse' />
    </div>
  )
}

interface ShipmentStatusCardsProps {
  onStatusClick?: (status: string) => void
}

/** Orders page: fetches its own copy of the statistics. */
export function ShipmentStatusCards({ onStatusClick }: ShipmentStatusCardsProps) {
  const { statistics, loading } = useOrderStatistics()

  return (
    <ShipmentStatusCardsView
      byStatus={statistics?.by_shipment_status ?? null}
      loading={loading}
      onStatusClick={onStatusClick}
    />
  )
}

/**
 * The grid itself, fed from outside, so the dashboard can render it from the
 * statistics payload it already has instead of fetching it again.
 *
 * With no `onStatusClick` the cards render as plain tiles: the orders list
 * does not read filters off the URL, so a click from another page has
 * nowhere to land.
 */
export function ShipmentStatusCardsView({
  byStatus,
  loading,
  onStatusClick,
}: {
  byStatus: Array<{ status: string; count: number }> | null
  loading: boolean
  onStatusClick?: (status: string) => void
}) {
  if (loading) {
    return (
      <div className='grid gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5'>
        {[...Array(5)].map((_, i) => (
          <ShipmentStatusCardSkeleton key={i} />
        ))}
      </div>
    )
  }

  if (!byStatus) {
    return null
  }

  const shipmentStats = byStatus.reduce(
    (acc, item) => {
      acc[item.status] = item.count
      return acc
    },
    {} as Record<string, number>
  )

  // Map stats to config and maintain process order
  const orderedStatuses = statusConfig
    .map((config) => ({
      ...config,
      count: shipmentStats[config.key] || 0,
    }))
    .sort((a, b) => a.order - b.order)

  return (
    <div className='grid gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5'>
      {orderedStatuses.map(({ key, label, icon: Icon, color, gradient, count }) => {
        const Tile = onStatusClick ? 'button' : 'div'
        return (
        <Tile
          key={key}
          onClick={onStatusClick ? () => onStatusClick(key) : undefined}
          className={`flex flex-col justify-between rounded-lg border border-muted/60 bg-gradient-to-bl ${gradient} to-card p-3 shadow-sm ${
            onStatusClick ? 'cursor-pointer transition-all hover:border-muted hover:shadow-md' : ''
          }`}
        >
          <div className='flex items-center justify-between mb-2'>
            <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground leading-none'>
              {label}
            </p>
            <span className={`shrink-0 ${color}`}>
              <Icon className='h-4 w-4' />
            </span>
          </div>
          <p className='text-sm font-bold tabular-nums'>{count}</p>
        </Tile>
        )
      })}
    </div>
  )
}
