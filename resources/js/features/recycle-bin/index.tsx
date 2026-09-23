import { useCallback, useEffect, useMemo, useState } from 'react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { usePermissions } from '@/hooks/use-permissions'
import {
  type TrashedClient,
  type TrashedInventoryItem,
  type TrashedOrder,
  lockRecycleBin,
  useRecycleBinCounts,
} from '@/hooks/useRecycleBin'
import { trashedClientsColumns } from './components/clients-columns'
import { trashedInventoryColumns } from './components/inventory-columns'
import { trashedOrdersColumns } from './components/orders-columns'
import { RecycleBinLock } from './components/recycle-bin-lock'
import { RecycleBinPanel } from './components/recycle-bin-panel'

export function RecycleBin() {
  const { can } = usePermissions()

  // Always starts locked. Deliberately component state rather than session or
  // local storage, so opening the page ever again asks for the PIN.
  const [unlocked, setUnlocked] = useState(false)
  const [lockNotice, setLockNotice] = useState<string | null>(null)

  const { counts, refreshCounts } = useRecycleBinCounts(unlocked)

  // Re-lock on the way out so the next visit cannot inherit this unlock.
  useEffect(() => {
    return () => {
      void lockRecycleBin()
    }
  }, [])

  // Any 423 from a listing drops straight back to the PIN prompt.
  const handleLocked = useCallback(() => {
    setUnlocked(false)
    setLockNotice('Your recycle bin session expired. Enter the PIN again.')
  }, [])

  const canOrders = can('delete orders')
  const canClients = can('delete client')
  // The inventory tab spans the catalog ('delete inventory') and per-client
  // stock, which is gated on 'delete client' everywhere else in the portal.
  const canInventory = can('delete inventory') || canClients

  const tabs = useMemo(
    () =>
      [
        canOrders ? { value: 'orders', label: 'Orders', count: counts.orders } : null,
        canClients ? { value: 'clients', label: 'Clients', count: counts.clients } : null,
        canInventory ? { value: 'inventory', label: 'Inventory', count: counts.inventory } : null,
      ].filter((tab): tab is { value: string; label: string; count: number } => tab !== null),
    [canOrders, canClients, canInventory, counts]
  )

  const [active, setActive] = useState(tabs[0]?.value ?? 'orders')

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4'>
        {/* No page heading: the sidebar and the tabs already name this screen,
            and it keeps the backdrop clean behind the PIN prompt.

            The prompt is a modal over the page; nothing below it loads until
            the PIN is accepted, so there is no data behind the overlay. */}
        <RecycleBinLock
          open={!unlocked}
          notice={lockNotice}
          onUnlocked={() => {
            setLockNotice(null)
            setUnlocked(true)
          }}
        />

        {!unlocked ? null : tabs.length === 0 ? (
          <p className='text-muted-foreground text-sm'>
            You do not have permission to view any deleted records.
          </p>
        ) : (
          <Tabs value={active} onValueChange={setActive} className='flex flex-1 flex-col gap-4'>
            <TabsList className='w-fit'>
              {tabs.map((tab) => (
                <TabsTrigger key={tab.value} value={tab.value} className='gap-1.5'>
                  {tab.label}
                  {tab.count > 0 && (
                    <Badge variant='secondary' className='px-1.5 text-[10px]'>
                      {tab.count}
                    </Badge>
                  )}
                </TabsTrigger>
              ))}
            </TabsList>

            {canOrders && (
              <TabsContent value='orders'>
                <RecycleBinPanel<TrashedOrder>
                  tab='orders'
                  columns={trashedOrdersColumns}
                  entityName='order'
                  entityLabel='Orders'
                  searchPlaceholder='Search by order number, customer, email or phone…'
                  emptyMessage='No deleted orders.'
                  totalInBin={counts.orders}
                  onChanged={refreshCounts}
                  onLocked={handleLocked}
                />
              </TabsContent>
            )}

            {canClients && (
              <TabsContent value='clients'>
                <RecycleBinPanel<TrashedClient>
                  tab='clients'
                  columns={trashedClientsColumns}
                  entityName='client'
                  entityLabel='Clients'
                  searchPlaceholder='Search by company, contact or client ID…'
                  emptyMessage='No deleted clients.'
                  totalInBin={counts.clients}
                  onChanged={refreshCounts}
                  onLocked={handleLocked}
                />
              </TabsContent>
            )}

            {canInventory && (
              <TabsContent value='inventory'>
                <RecycleBinPanel<TrashedInventoryItem>
                  tab='inventory'
                  columns={trashedInventoryColumns}
                  entityName='item'
                  entityLabel='Inventory'
                  searchPlaceholder='Search by name, SKU or code…'
                  emptyMessage='No deleted inventory.'
                  totalInBin={counts.inventory}
                  // Catalog and client-stock ids collide, so identity is composite.
                  getRowId={(row) => row.row_id}
                  onChanged={refreshCounts}
                  onLocked={handleLocked}
                />
              </TabsContent>
            )}
          </Tabs>
        )}
      </Main>
    </>
  )
}
