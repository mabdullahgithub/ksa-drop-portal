import { useCallback, useEffect, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { usePermissions } from '@/hooks/use-permissions'
import { PinLock } from '@/components/pin-lock'
import { useScreenLock } from '@/hooks/use-screen-lock'
import {
  type RequestFilters,
  type RequestTab,
  lockRequests,
  unlockRequests,
  useRequestCounts,
} from '@/hooks/useInventoryRequests'
import { DetailsPanel } from './components/details-panel'
import { ProductsPanel } from './components/products-panel'
import { StockPanel } from './components/stock-panel'

/**
 * Everything clients are waiting on, in three queues: products they added,
 * and the stock and details changes they asked for on verified ones.
 */
export function Requests() {
  const { can } = usePermissions()

  // Each tab is a permission of its own.
  const canProducts = can('view product requests')
  const canStock = can('view stock requests')
  const canDetails = can('view detail requests')

  // Behind the PIN like the recycle bin and the WhatsApp inbox, unless its
  // lock is switched off in .env. Always starts locked: component state, not
  // storage, so opening the page again asks for the PIN.
  const locked = useScreenLock('requests')
  const [unlocked, setUnlocked] = useState(!locked)
  const [lockNotice, setLockNotice] = useState<string | null>(null)

  // Re-lock on the way out so the next visit cannot inherit this unlock.
  useEffect(() => {
    if (!locked) return
    return () => {
      void lockRequests()
    }
  }, [locked])

  // Any 423 from a queue drops straight back to the PIN prompt.
  const handleLocked = useCallback(() => {
    setUnlocked(false)
    setLockNotice('Your requests session expired. Enter the PIN again.')
  }, [])

  const { counts, clients, refreshCounts } = useRequestCounts(
    unlocked && (canProducts || canStock || canDetails),
    handleLocked
  )

  const tabs = useMemo(
    () =>
      [
        canProducts ? { value: 'products' as const, label: 'New products' } : null,
        canStock ? { value: 'stock' as const, label: 'Stock updates' } : null,
        canDetails ? { value: 'details' as const, label: 'Details changes' } : null,
      ].filter((tab): tab is { value: RequestTab; label: string } => tab !== null),
    [canProducts, canStock, canDetails]
  )

  // A notification or a badge elsewhere opens the page on a tab, a client or
  // a status: /requests?tab=stock&client=12.
  const { asked, initialFilters } = useMemo(() => {
    const params = new URLSearchParams(window.location.search)
    const filters: Partial<RequestFilters> = {}
    if (params.get('client')) filters.client_id = params.get('client') as string
    if (params.get('status')) filters.status = params.get('status') as string

    return { asked: params.get('tab'), initialFilters: filters }
  }, [])

  const [active, setActive] = useState<string>(
    tabs.find((tab) => tab.value === asked)?.value ?? tabs[0]?.value ?? 'products'
  )

  // Deciding something changes the tab counts and the number in the sidebar.
  const handleChanged = () => {
    refreshCounts()
    router.reload({ only: ['pendingRequestsCount'] })
  }

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4'>
        <div>
          <h2 className='text-2xl font-bold tracking-tight'>Requests</h2>
          <p className='text-muted-foreground text-sm'>
            What clients are waiting on: new products, and changes to verified ones.
          </p>
        </div>

        {/* The prompt is a modal over the page; nothing below it loads until
            the PIN is accepted, so there is no data behind the overlay. */}
        <PinLock
          open={!unlocked}
          title='Requests are locked'
          description='Enter the PIN to open the requests.'
          unlock={unlockRequests}
          notice={lockNotice}
          onUnlocked={() => {
            setLockNotice(null)
            setUnlocked(true)
          }}
        />

        {!unlocked ? null : tabs.length === 0 ? (
          <p className='text-muted-foreground text-sm'>You do not have permission to view any requests.</p>
        ) : (
          <Tabs value={active} onValueChange={setActive} className='flex flex-1 flex-col gap-4'>
            <TabsList className='w-fit'>
              {tabs.map((tab) => (
                <TabsTrigger key={tab.value} value={tab.value} className='gap-1.5'>
                  {tab.label}
                  {(counts[tab.value] ?? 0) > 0 && (
                    <Badge variant='secondary' className='px-1.5 text-[10px]'>
                      {counts[tab.value]}
                    </Badge>
                  )}
                </TabsTrigger>
              ))}
            </TabsList>

            {canProducts && (
              <TabsContent value='products'>
                <ProductsPanel clients={clients} initialFilters={initialFilters} onChanged={handleChanged} onLocked={handleLocked} />
              </TabsContent>
            )}

            {canStock && (
              <TabsContent value='stock'>
                <StockPanel clients={clients} initialFilters={initialFilters} onChanged={handleChanged} onLocked={handleLocked} />
              </TabsContent>
            )}

            {canDetails && (
              <TabsContent value='details'>
                <DetailsPanel clients={clients} initialFilters={initialFilters} onChanged={handleChanged} onLocked={handleLocked} />
              </TabsContent>
            )}
          </Tabs>
        )}
      </Main>
    </>
  )
}
