import { useState } from 'react'
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
import { OrdersStats } from './components/orders-stats'
import { useOrders } from '@/hooks/useOrders'

export function Orders() {
  const { orders, meta, loading, filters, updateFilters, refresh } = useOrders({
    per_page: 15,
    sort_by: 'created_at',
    sort_order: 'desc',
  })
  const [tableInstance, setTableInstance] = useState<any>(null)

  return (
    <OrdersProvider>
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

        <OrdersStats />

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
    </OrdersProvider>
  )
}
