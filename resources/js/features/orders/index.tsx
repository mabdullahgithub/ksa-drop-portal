import { useState, useEffect } from 'react'
import { HelpCircle } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { OrdersDialogs } from './components/orders-dialogs'
import { OrdersPrimaryButtons } from './components/orders-primary-buttons'
import { OrdersProvider } from './components/orders-provider'
import { OrdersTable } from './components/orders-table'
import { OrdersFilters } from './components/orders-filters'
import { ShipmentStatusCards } from './components/shipment-status-cards'
import { TagStatCards } from './components/tag-stat-cards'
import { ShipmentStatusInfoModal } from './components/shipment-status-info-modal'
import { useOrders } from '@/hooks/useOrders'

export function Orders() {
  const { orders, meta, loading, filters, updateFilters, refresh } = useOrders({
    per_page: 15,
    sort_by: 'created_at',
    sort_order: 'desc',
    has_shipment: false,
  })
  const [tableInstance, setTableInstance] = useState<any>(null)
  const [statusInfoModalOpen, setStatusInfoModalOpen] = useState(false)
  const [stats, setStats] = useState<any>(null)

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const response = await fetch('/api/orders/statistics')
        const data = await response.json()
        setStats(data)
      } catch (error) {
        console.error('Error fetching statistics:', error)
      }
    }
    fetchStats()
  }, [])

  const activeTab = filters.has_shipment === true ? 'assigned' : filters.has_shipment === false ? 'unassigned' : 'unassigned'

  const handleTabChange = (tab: 'unassigned' | 'assigned') => {
    updateFilters({
      has_shipment: tab === 'assigned',
      page: 1,
    })
  }

  return (
    <OrdersProvider refresh={refresh}>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Orders</h2>
            <p className='text-muted-foreground text-sm'>
              Manage your orders and track their status.
            </p>
          </div>
          <OrdersPrimaryButtons />
        </div>

        <TagStatCards
          activeTag={filters.tags?.[0] ?? null}
          onTagClick={(tagName) => {
            const isActive = filters.tags?.[0] === tagName
            updateFilters({ tags: isActive ? [] : [tagName], page: 1 })
          }}
        />

        <div>
          <div className='flex items-center justify-between mb-3'>
            <h3 className='text-sm font-semibold'>J&T Shipment Status Distribution</h3>
            <button
              onClick={() => setStatusInfoModalOpen(true)}
              className='inline-flex items-center gap-1.5 rounded-md border border-muted px-2.5 py-1.5 text-xs font-medium text-muted-foreground hover:bg-muted/50 transition-colors'
            >
              <HelpCircle className='h-3.5 w-3.5' />
              Info
            </button>
          </div>
          <ShipmentStatusCards
            onStatusClick={(status) => {
              updateFilters({ shipment_status: [status], has_shipment: true, page: 1 })
            }}
          />
        </div>

        {/* Tabs for Unassigned / Assigned to Courier */}
        <div className='flex gap-2 border-b border-muted/50'>
          <button
            onClick={() => handleTabChange('unassigned')}
            className={`px-4 py-2 text-sm font-medium transition-colors ${
              activeTab === 'unassigned'
                ? 'border-b-2 border-primary text-primary -mb-px'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            All Orders
            {stats?.unassigned_orders != null && (
              <span className='ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium tabular-nums'>
                {stats.unassigned_orders}
              </span>
            )}
          </button>
          <button
            onClick={() => handleTabChange('assigned')}
            className={`px-4 py-2 text-sm font-medium transition-colors ${
              activeTab === 'assigned'
                ? 'border-b-2 border-primary text-primary -mb-px'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            Assigned to Courier
            {stats?.assigned_orders != null && (
              <span className='ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium tabular-nums'>
                {stats.assigned_orders}
              </span>
            )}
          </button>
        </div>

        <OrdersFilters
          filters={filters}
          onFiltersChange={updateFilters}
          table={tableInstance}
        />

        <OrdersTable
          data={orders}
          meta={meta}
          loading={loading}
          onRefresh={refresh}
          onPageChange={(page) => updateFilters({ page })}
          onPageSizeChange={(pageSize) => updateFilters({ per_page: pageSize, page: 1 })}
          onSortChange={(sortBy, sortOrder) => updateFilters({ sort_by: sortBy, sort_order: sortOrder, page: 1 })}
          onTableReady={setTableInstance}
        />
      </Main>

      <OrdersDialogs onSuccess={refresh} />
      <ShipmentStatusInfoModal open={statusInfoModalOpen} onOpenChange={setStatusInfoModalOpen} />
    </OrdersProvider>
  )
}
