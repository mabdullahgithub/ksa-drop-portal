import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { useClients, useClientStatistics, useClientFilterOptions } from '@/hooks/useClients'
import { ClientProvider, useClientContext } from './components/client-provider'
import { ClientStats } from './components/client-stats'
import { ClientTable } from './components/client-table'
import { ClientFiltersComponent } from './components/client-filters'
import { ClientPrimaryButtons } from './components/client-primary-buttons'
import { ClientBulkActions } from './components/client-bulk-actions'
import { ClientDialogs } from './components/client-dialogs'

export function Client() {
  return (
    <ClientProvider>
      <ClientContent />
    </ClientProvider>
  )
}

function ClientContent() {
  const { clients, meta, loading, filters, updateFilters, refresh } = useClients()
  const { stats, loading: statsLoading, refresh: refreshStats } = useClientStatistics()
  const filterOptions = useClientFilterOptions()
  const { selectedRows } = useClientContext()

  const handlePageChange = (page: number) => updateFilters({ page })
  const handlePageSizeChange = (perPage: number) => updateFilters({ per_page: perPage, page: 1 })
  const handleSortChange = (sortBy: string, sortOrder: 'asc' | 'desc') => updateFilters({ sort_by: sortBy, sort_order: sortOrder })

  const handleSuccess = () => {
    refresh()
    refreshStats()
  }

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='mb-4 flex items-center justify-between'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>Clients</h1>
            <p className='text-muted-foreground'>
              Manage your dropshipper and fulfilment clients
            </p>
          </div>
          <ClientPrimaryButtons filters={filters} onRefresh={handleSuccess} />
        </div>

        <div className='space-y-4'>
          <ClientStats stats={stats} loading={statsLoading} />

          <ClientFiltersComponent
            filters={filters}
            filterOptions={filterOptions}
            onFiltersChange={updateFilters}
          />

          <ClientBulkActions selectedIds={selectedRows} onSuccess={handleSuccess} />

          <ClientTable
            data={clients}
            meta={meta}
            loading={loading}
            onPageChange={handlePageChange}
            onPageSizeChange={handlePageSizeChange}
            onSortChange={handleSortChange}
          />
        </div>

        <ClientDialogs onSuccess={handleSuccess} />
      </Main>
    </>
  )
}
