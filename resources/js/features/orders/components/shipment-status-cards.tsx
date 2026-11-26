import type { OrderStatistics } from '@/types/order'
import { AlertCircle, CheckCircle2, MapPin, Package, PackageCheck, RotateCcw, Truck, Undo2, XCircle } from 'lucide-react'
import { StatCard, StatCardSkeleton, type StatCardTone } from '@/components/stat-card'

/** Solid stat-card grid, with a little air between the two rows. */
const GRID = 'grid grid-cols-2 gap-x-2 gap-y-2.5 sm:grid-cols-3 lg:grid-cols-6'

// Ordered by logical process flow. Neighbours get distinct fills.
const statusConfig: Array<{ key: string; label: string; icon: React.ElementType; tone: StatCardTone }> = [
  { key: 'pending', label: 'Pending', icon: Package, tone: 'slate' },
  { key: 'info_received', label: 'Info Received', icon: PackageCheck, tone: 'sky' },
  { key: 'in_transit', label: 'In Transit', icon: Truck, tone: 'indigo' },
  { key: 'out_for_delivery', label: 'Out for Delivery', icon: MapPin, tone: 'orange' },
  { key: 'delivered', label: 'Delivered', icon: CheckCircle2, tone: 'emerald' },
  { key: 'attempt_fail', label: 'Attempt Failed', icon: AlertCircle, tone: 'rose' },
  { key: 'returning', label: 'Returning', icon: Undo2, tone: 'amber' },
  { key: 'returned', label: 'Returned', icon: RotateCcw, tone: 'yellow' },
  { key: 'exception', label: 'Exception', icon: AlertCircle, tone: 'red' },
  { key: 'cancelled', label: 'Cancelled', icon: XCircle, tone: 'zinc' },
  { key: 'failed', label: 'Failed', icon: XCircle, tone: 'red' },
]

interface ShipmentStatusCardsProps {
  statistics: OrderStatistics | null
  loading: boolean
  activeStatus?: string | null
  onStatusClick?: (status: string) => void
}

/** Orders page: fed the filter-aware statistics the page already holds. */
export function ShipmentStatusCards({ statistics, loading, activeStatus, onStatusClick }: ShipmentStatusCardsProps) {
  return (
    <ShipmentStatusCardsView
      byStatus={statistics?.by_shipment_status ?? null}
      // Skeletons only on the first load; a refetch keeps the old numbers up.
      loading={loading && !statistics}
      activeStatus={activeStatus}
      onStatusClick={onStatusClick}
    />
  )
}

/**
 * The grid itself, fed from outside, shared by the admin orders page and the
 * client portal. With no `onStatusClick` the cards render as plain tiles.
 */
export function ShipmentStatusCardsView({
  byStatus,
  loading,
  activeStatus,
  onStatusClick,
}: {
  byStatus: Array<{ status: string; count: number }> | null
  loading: boolean
  activeStatus?: string | null
  onStatusClick?: (status: string) => void
}) {
  if (loading) {
    return (
      <div className={GRID}>
        {[...Array(6)].map((_, i) => (
          <StatCardSkeleton key={i} />
        ))}
      </div>
    )
  }

  if (!byStatus) return null

  const counts: Record<string, number> = {}
  for (const item of byStatus) counts[item.status] = item.count

  return (
    <div className={GRID}>
      {statusConfig.map(({ key, label, icon: Icon, tone }) => (
        <StatCard
          key={key}
          icon={<Icon className='h-3.5 w-3.5' />}
          label={label}
          value={(counts[key] ?? 0).toLocaleString()}
          tone={tone}
          texture='dots'
          active={activeStatus === key}
          onClick={onStatusClick ? () => onStatusClick(key) : undefined}
        />
      ))}
    </div>
  )
}
