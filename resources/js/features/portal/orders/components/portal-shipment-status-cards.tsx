import type { PortalOrderStatistics } from '@/hooks/usePortal'
import { ShipmentStatusCardsView } from '@/features/orders/components/shipment-status-cards'

interface PortalShipmentStatusCardsProps {
  /** Status counts for the current filters; the cards ignore the status filter they drive. */
  statistics?: PortalOrderStatistics | null
  loading?: boolean
  activeStatus?: string | null
  onStatusClick?: (status: string) => void
}

export function PortalShipmentStatusCards({ statistics, loading = false, activeStatus, onStatusClick }: PortalShipmentStatusCardsProps) {
  return (
    <ShipmentStatusCardsView
      byStatus={statistics?.by_shipment_status ?? null}
      // Keep the last numbers on screen while a filter change is in flight.
      loading={loading && !statistics}
      activeStatus={activeStatus}
      onStatusClick={onStatusClick}
    />
  )
}
