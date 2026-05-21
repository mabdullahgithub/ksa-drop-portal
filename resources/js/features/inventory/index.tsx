import { useState } from 'react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { InventoryDialogs } from './components/inventory-dialogs'
import { InventoryPrimaryButtons } from './components/inventory-primary-buttons'
import { InventoryProvider, useInventoryContext } from './components/inventory-provider'
import { InventoryTable } from './components/inventory-table'
import { InventoryCardView } from './components/inventory-card-view'
import { InventoryFilters } from './components/inventory-filters'
import { InventoryStats } from './components/inventory-stats'
import { useProducts } from '@/hooks/useProducts'

function InventoryContent() {
  const { products, meta, loading, filters, updateFilters, refresh } = useProducts({
    per_page: 20,
    sort_by: 'created_at',
    sort_order: 'desc',
  })
  const [tableInstance, setTableInstance] = useState<any>(null)
  const { viewMode } = useInventoryContext()

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Inventory</h2>
            <p className='text-muted-foreground text-sm'>
              Manage your products and track stock levels.
            </p>
          </div>
          <InventoryPrimaryButtons onImportSuccess={refresh} />
        </div>

        <InventoryStats />

        <InventoryFilters
          filters={filters}
          onFiltersChange={updateFilters}
          table={viewMode === 'table' ? tableInstance : undefined}
        />

        {viewMode === 'table' ? (
          <InventoryTable
            data={products}
            meta={meta}
            loading={loading}
            onPageChange={(page) => updateFilters({ page })}
            onPageSizeChange={(pageSize) => updateFilters({ per_page: pageSize, page: 1 })}
            onSortChange={(sortBy, sortOrder) => updateFilters({ sort_by: sortBy, sort_order: sortOrder, page: 1 })}
            onTableReady={setTableInstance}
          />
        ) : (
          <InventoryCardView
            data={products}
            meta={meta}
            loading={loading}
            onPageChange={(page) => updateFilters({ page })}
            onPageSizeChange={(pageSize) => updateFilters({ per_page: pageSize, page: 1 })}
          />
        )}
      </Main>

      <InventoryDialogs />
    </>
  )
}

export function Inventory() {
  return (
    <InventoryProvider>
      <InventoryContent />
    </InventoryProvider>
  )
}
