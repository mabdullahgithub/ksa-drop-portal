import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import {
  Activity,
  Banknote,
  Bike,
  HandCoins,
  LayoutGrid,
  Package,
  PackageCheck,
  Plus,
  Radio,
  Search as SearchIcon,
  Smartphone,
  Table2,
  Trophy,
  UsersRound,
  Wallet,
  Warehouse,
  X,
} from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Can } from '@/components/can'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { EmptyState } from '@/components/empty-state'
import { MultiSelectFilter } from '@/components/multi-select-filter'
import { SearchBeam } from '@/components/search-beam'
import { StatCard } from '@/components/stat-card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { usePermissions } from '@/hooks/use-permissions'
import { RiderAccessDialog } from './components/rider-access-dialog'
import { RiderCard } from './components/rider-card'
import { RiderFormDialog } from './components/rider-form-dialog'
import { RiderOrdersSheet } from './components/rider-orders-sheet'
import { RiderPaymentsSheet } from './components/rider-payments-sheet'
import { RiderSupportButton } from './components/rider-support-button'
import { AppState, appStateOf, CashDue, PayDue, RiderAvatar, RowActions, sar, type RiderDialog } from './components/rider-parts'
import { TopPerformers } from './components/top-performers'
import { RIDER_ROLES, type RiderRow, type RiderSupportContact, type WarehouseOption } from './data/types'

const STATUS_OPTIONS = [
  { value: 'active', label: 'Active' },
  { value: 'suspended', label: 'Suspended' },
]

const APP_OPTIONS = [
  { value: 'online', label: 'Online now' },
  { value: 'signed_in', label: 'Signed in' },
  { value: 'link_sent', label: 'Link sent, not opened' },
  { value: 'not_signed_in', label: 'Not signed in' },
  { value: 'pin_locked', label: 'PIN locked' },
]

const ROLE_OPTIONS = RIDER_ROLES.map(({ value, label }) => ({ value, label }))

const NO_HUB = 'none'

/** The stat cards show the currency as a small unit ahead of the figure. */
const amount = (n: number) => sar(n).replace('SAR ', '')

export function Riders({
  riders: initial,
  warehouses,
  support: initialSupport,
}: {
  riders: RiderRow[]
  warehouses: WarehouseOption[]
  support: RiderSupportContact | null
}) {
  const { can } = usePermissions()
  const canManage = can('manage riders')

  const [riders, setRiders] = useState(initial)
  const [support, setSupport] = useState(initialSupport)
  const [view, setView] = useState<'cards' | 'table'>('cards')
  const [query, setQuery] = useState('')
  const [statuses, setStatuses] = useState<string[]>([])
  const [roles, setRoles] = useState<string[]>([])
  const [appStates, setAppStates] = useState<string[]>([])
  const [hubs, setHubs] = useState<string[]>([])
  const [dialog, setDialog] = useState<RiderDialog | null>(null)
  const [busy, setBusy] = useState(false)
  // Closed on every visit: its numbers are only worked out when asked for.
  const [showPerformers, setShowPerformers] = useState(false)

  const hubOptions = useMemo(
    () => [...warehouses.map((w) => ({ value: String(w.id), label: w.name })), { value: NO_HUB, label: 'No hub' }],
    [warehouses]
  )

  const upsert = (rider: RiderRow) =>
    setRiders((list) => {
      const exists = list.some((r) => r.id === rider.id)
      const next = exists ? list.map((r) => (r.id === rider.id ? rider : r)) : [...list, rider]
      return next.sort((a, b) => a.name.localeCompare(b.name))
    })

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    return riders.filter((r) => {
      if (statuses.length && !statuses.includes(r.status)) return false
      if (roles.length && !roles.includes(r.role)) return false
      if (appStates.length && !appStates.some((s) => (s === 'online' ? r.online : s === appStateOf(r)))) return false
      if (hubs.length && !hubs.includes(r.warehouse_id ? String(r.warehouse_id) : NO_HUB)) return false
      if (!q) return true
      return [r.name, r.name_ar, r.phone, r.phone_local, r.city, r.warehouse_name, r.vehicle_plate]
        .filter(Boolean)
        .some((v) => String(v).toLowerCase().includes(q))
    })
  }, [riders, query, statuses, roles, appStates, hubs])

  const filtering = query.trim() !== '' || statuses.length > 0 || roles.length > 0 || appStates.length > 0 || hubs.length > 0
  const clearFilters = () => {
    setQuery('')
    setStatuses([])
    setRoles([])
    setAppStates([])
    setHubs([])
  }

  const totals = useMemo(
    () => ({
      // Inventory managers are on the page, but they aren't riders.
      active: riders.filter((r) => r.status === 'active' && r.role === 'rider').length,
      signedIn: riders.filter((r) => r.device).length,
      online: riders.filter((r) => r.online).length,
      held: riders.reduce((sum, r) => sum + r.stats.held, 0),
      delivered: riders.reduce((sum, r) => sum + r.stats.delivered, 0),
      cash: riders.reduce((sum, r) => sum + r.stats.cash_collected, 0),
      // Riders in credit don't offset what the others owe.
      owed: riders.reduce((sum, r) => sum + Math.max(r.cash.balance, 0), 0),
      pay: riders.reduce((sum, r) => sum + Math.max(r.pay.balance, 0), 0),
    }),
    [riders]
  )

  const runAction = async (request: () => Promise<{ data: { message: string; rider?: RiderRow } }>, remove?: number) => {
    setBusy(true)
    try {
      const { data } = await request()
      toast.success(data.message)
      if (remove) setRiders((list) => list.filter((r) => r.id !== remove))
      else if (data.rider) upsert(data.rider)
      setDialog(null)
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Something went wrong.')
    } finally {
      setBusy(false)
    }
  }

  // Keep the green dots current: who has the app open, every 30 seconds.
  useEffect(() => {
    const poll = async () => {
      if (document.visibilityState !== 'visible') return
      try {
        const { data } = await axios.get<{ online: number[] }>('/api/riders/presence')
        const online = new Set(data.online)
        setRiders((list) => list.map((r) => (r.online === online.has(r.id) ? r : { ...r, online: online.has(r.id) })))
      } catch {
        // Keep the last dots; the next poll will catch up.
      }
    }
    const timer = window.setInterval(poll, 30_000)
    document.addEventListener('visibilitychange', poll)
    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', poll)
    }
  }, [])

  const current = dialog && 'rider' in dialog ? dialog.rider : null

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div className='space-y-1'>
            <h2 className='text-3xl font-bold tracking-tight'>Riders</h2>
            <p className='text-muted-foreground'>KSA Express riders, inventory managers and the app on their phones.</p>
          </div>
          <div className='flex items-center gap-2'>
            {!showPerformers && riders.length > 0 && (
              <Button variant='outline' onClick={() => setShowPerformers(true)}>
                <Trophy size={16} className='me-1 text-amber-600 dark:text-amber-400' />
                Top performers
              </Button>
            )}
            <RiderSupportButton support={support} canManage={canManage} onChange={setSupport} />
            <Can permission='manage riders'>
              <Button onClick={() => setDialog({ type: 'add' })}>
                <Plus size={16} className='me-1' />
                Add rider
              </Button>
            </Can>
          </div>
        </div>

        {/* The dashboard's stat cards. Never more than four across: the page is
            capped at 1280px, and an amount like "SAR 12,345.00" needs the width.

            Eight cards, so the tones are not the dashboard's row of six. This
            order was computed so that every card differs from the ones beside,
            above and below it, two across and four across alike (worst
            neighbours: ΔE 9.1 colour-blind, 21.9 normal vision). Moving a
            card, or changing the column counts, means working it out again. */}
        <div className='grid grid-cols-2 gap-x-2 gap-y-0.5 lg:grid-cols-4'>
          <StatCard
            icon={<Bike className='h-3.5 w-3.5' />}
            label='Active riders'
            value={totals.active}
            tone='blue'
            hint='Riders who are not suspended. Inventory managers are not counted.'
          />
          <StatCard
            icon={<Radio className='h-3.5 w-3.5' />}
            label='Online now'
            value={totals.online}
            tone='emerald'
            hint='Have the app open on their phone right now.'
          />
          <StatCard
            icon={<Smartphone className='h-3.5 w-3.5' />}
            label='Signed in to the app'
            value={totals.signedIn}
            tone='fuchsia'
            hint='Have the app signed in on a phone, open or not.'
          />
          <StatCard
            icon={<Package className='h-3.5 w-3.5' />}
            label='Parcels with riders'
            value={totals.held}
            tone='orange'
            hint='Every parcel in a rider’s hands, including ones to hand back at the hub.'
          />
          <StatCard
            icon={<PackageCheck className='h-3.5 w-3.5' />}
            label='Delivered today'
            value={totals.delivered}
            tone='teal'
          />
          <StatCard
            icon={<Banknote className='h-3.5 w-3.5' />}
            label='Cash collected today'
            value={amount(totals.cash)}
            pre='SAR'
            tone='violet'
            hint='Cash taken on today’s deliveries. Card and transfer payments are not counted.'
          />
          <StatCard
            icon={<HandCoins className='h-3.5 w-3.5' />}
            label='Cash riders owe'
            value={amount(totals.owed)}
            pre='SAR'
            tone='rose'
            hint='Cash still to hand in. Riders in credit don’t reduce it.'
          />
          <StatCard
            icon={<Wallet className='h-3.5 w-3.5' />}
            label='Pay owed to riders'
            value={amount(totals.pay)}
            pre='SAR'
            tone='sky'
            hint='What KSA Drop still owes riders for their visits. Overpaid riders don’t reduce it.'
          />
        </div>

        {showPerformers && riders.length > 0 && <TopPerformers onClose={() => setShowPerformers(false)} />}

        {riders.length === 0 ? (
          <EmptyState
            bot='pill'
            title='No riders yet'
            description='Add a rider with just their name and phone, then send them the app link.'
            action={
              canManage && (
                <Button onClick={() => setDialog({ type: 'add' })} size='sm'>
                  <Plus size={16} className='me-1' />
                  Add rider
                </Button>
              )
            }
            className='py-10'
          />
        ) : (
          <>
            {/* Filters */}
            <div className='flex flex-wrap items-center gap-2'>
              <div className='relative min-w-[200px] flex-1 sm:max-w-xs'>
                <SearchIcon className='pointer-events-none absolute top-1/2 z-10 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground inset-s-2.5' />
                <SearchBeam>
                  <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder='Search name, phone, plate…'
                    className='h-9 ps-8 text-sm'
                  />
                </SearchBeam>
              </div>
              <MultiSelectFilter label='Status' icon={Activity} options={STATUS_OPTIONS} selected={statuses} onChange={setStatuses} />
              {riders.some((r) => r.role === 'inventory_manager') && (
                <MultiSelectFilter label='Role' icon={UsersRound} options={ROLE_OPTIONS} selected={roles} onChange={setRoles} />
              )}
              <MultiSelectFilter label='App' icon={Smartphone} options={APP_OPTIONS} selected={appStates} onChange={setAppStates} />
              {warehouses.length > 0 && (
                <MultiSelectFilter label='Hub' icon={Warehouse} options={hubOptions} selected={hubs} onChange={setHubs} />
              )}
              {filtering && (
                <Button variant='ghost' size='sm' onClick={clearFilters} className='h-9'>
                  <X className='me-1 h-3.5 w-3.5' />
                  Reset
                </Button>
              )}
              <span className='ms-auto text-sm text-muted-foreground'>
                {filtering ? `${filtered.length} of ${riders.length}` : riders.length} rider{riders.length === 1 ? '' : 's'}
              </span>
              <div className='flex shrink-0 items-center overflow-hidden rounded-md border'>
                <Button
                  variant={view === 'cards' ? 'secondary' : 'ghost'}
                  size='sm'
                  className='h-9 rounded-none px-2.5'
                  onClick={() => setView('cards')}
                  title='Card view'
                >
                  <LayoutGrid className='h-4 w-4' />
                </Button>
                <Button
                  variant={view === 'table' ? 'secondary' : 'ghost'}
                  size='sm'
                  className='h-9 rounded-none px-2.5'
                  onClick={() => setView('table')}
                  title='Table view'
                >
                  <Table2 className='h-4 w-4' />
                </Button>
              </div>
            </div>

            {filtered.length === 0 ? (
              <div className='rounded-md border'>
                <EmptyState
                  bot='droid'
                  title='No riders found.'
                  description='No rider matches this search or these filters.'
                  action={
                    <Button variant='outline' size='sm' onClick={clearFilters}>
                      <X className='me-1 h-3.5 w-3.5' />
                      Reset filters
                    </Button>
                  }
                  className='py-10'
                />
              </div>
            ) : view === 'cards' ? (
              <div className='grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4'>
                {filtered.map((rider) => (
                  <RiderCard key={rider.id} rider={rider} canManage={canManage} onPick={setDialog} />
                ))}
              </div>
            ) : (
              <div className='overflow-hidden rounded-md border'>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Rider</TableHead>
                      <TableHead>App</TableHead>
                      <TableHead className='text-end'>With rider</TableHead>
                      <TableHead className='hidden text-end md:table-cell'>Today</TableHead>
                      <TableHead className='hidden text-end lg:table-cell'>Cash today</TableHead>
                      <TableHead className='text-end'>To hand in</TableHead>
                      <TableHead className='hidden text-end xl:table-cell'>Pay owed</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className='w-10' />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filtered.map((rider) => (
                      <TableRow key={rider.id}>
                        <TableCell>
                          <div className='flex items-center gap-3'>
                            <RiderAvatar name={rider.name} photoUrl={rider.photo_url} online={rider.online} className='size-8 text-xs' />
                            <div>
                              <div className='flex items-center gap-2 font-medium'>
                                {rider.name}
                                {rider.role === 'inventory_manager' && (
                                  <Badge variant='outline' className='border-blue-300 text-blue-700 dark:text-blue-400'>
                                    Inventory manager
                                  </Badge>
                                )}
                              </div>
                              <div className='text-xs text-muted-foreground' dir='ltr'>
                                {rider.phone_local}
                                {rider.warehouse_name && <span dir='auto'> · {rider.warehouse_name}</span>}
                              </div>
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <AppState rider={rider} />
                        </TableCell>
                        {rider.role === 'inventory_manager' ? (
                          // No parcels, cash or pay of their own: what they scanned today instead.
                          <TableCell colSpan={5} className='text-end text-sm tabular-nums text-muted-foreground'>
                            {rider.stock_today?.out.parcels ?? 0} out · {rider.stock_today?.in.parcels ?? 0} in today
                          </TableCell>
                        ) : (
                        <>
                        <TableCell className='text-end tabular-nums'>
                          <button
                            type='button'
                            onClick={() => setDialog({ type: 'orders', rider })}
                            className='rounded-md px-1.5 py-0.5 hover:bg-muted/60'
                            title='View orders'
                          >
                            {rider.stats.held}
                          </button>
                          {rider.stats.to_return > 0 && (
                            <div className='text-xs text-amber-700 dark:text-amber-400'>{rider.stats.to_return} to hand back</div>
                          )}
                        </TableCell>
                        <TableCell className='hidden text-end text-sm tabular-nums md:table-cell'>
                          <button
                            type='button'
                            onClick={() => setDialog({ type: 'orders', rider, outcome: 'delivered' })}
                            className='rounded-md px-1.5 py-0.5 text-green-700 hover:bg-muted/60 dark:text-green-400'
                            title='View delivered orders'
                          >
                            {rider.stats.delivered} delivered
                          </button>
                          {rider.stats.failed > 0 && (
                            <button
                              type='button'
                              onClick={() => setDialog({ type: 'orders', rider, outcome: 'attempt_fail' })}
                              className='rounded-md px-1.5 py-0.5 text-red-600 hover:bg-muted/60'
                              title='View failed orders'
                            >
                              {rider.stats.failed} failed
                            </button>
                          )}
                        </TableCell>
                        <TableCell className='hidden text-end tabular-nums lg:table-cell'>{sar(rider.stats.cash_collected)}</TableCell>
                        <TableCell className='text-end'>
                          <button
                            type='button'
                            onClick={() => setDialog({ type: 'payments', rider })}
                            className='rounded-md px-1.5 py-0.5 text-sm hover:bg-muted/60'
                            title='Cash & payments'
                          >
                            <CashDue cash={rider.cash} />
                          </button>
                        </TableCell>
                        <TableCell className='hidden text-end xl:table-cell'>
                          <button
                            type='button'
                            onClick={() => setDialog({ type: 'payments', rider, direction: 'out' })}
                            className='rounded-md px-1.5 py-0.5 text-sm hover:bg-muted/60'
                            title='Pay to rider'
                          >
                            <PayDue pay={rider.pay} />
                          </button>
                        </TableCell>
                        </>
                        )}
                        <TableCell>
                          {rider.status === 'active' ? (
                            <Badge variant='outline' className='border-green-300 text-green-700 dark:text-green-400'>Active</Badge>
                          ) : (
                            <Badge variant='outline' className='border-red-300 text-red-700 dark:text-red-400'>Suspended</Badge>
                          )}
                        </TableCell>
                        <TableCell>{canManage && <RowActions rider={rider} onPick={setDialog} />}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </>
        )}
      </Main>

      {(dialog?.type === 'add' || dialog?.type === 'edit') && (
        <RiderFormDialog
          key={dialog.type === 'edit' ? `edit-${dialog.rider.id}` : 'add'}
          open
          onOpenChange={(open) => !open && setDialog(null)}
          rider={dialog.type === 'edit' ? dialog.rider : null}
          warehouses={warehouses}
          onSaved={(rider, created) => {
            upsert(rider)
            // Next step for a new rider: get the app onto their phone.
            if (created) setTimeout(() => setDialog({ type: 'link', rider }), 150)
          }}
        />
      )}

      {(dialog?.type === 'link' || dialog?.type === 'pin') && (
        <RiderAccessDialog
          key={`${dialog.type}-${dialog.rider.id}`}
          kind={dialog.type}
          rider={dialog.rider}
          open
          onOpenChange={(open) => !open && setDialog(null)}
          onChanged={upsert}
        />
      )}

      {dialog?.type === 'orders' && (
        <RiderOrdersSheet
          key={`${dialog.rider.id}:${dialog.outcome ?? 'all'}`}
          rider={dialog.rider}
          outcome={dialog.outcome}
          open
          onOpenChange={(open) => !open && setDialog(null)}
        />
      )}

      {dialog?.type === 'payments' && (
        <RiderPaymentsSheet
          key={dialog.rider.id}
          rider={dialog.rider}
          initialDirection={dialog.direction}
          onEditRates={canManage ? () => setDialog({ type: 'edit', rider: dialog.rider }) : undefined}
          open
          onOpenChange={(open) => !open && setDialog(null)}
          onChanged={(balances) => setRiders((list) => list.map((r) => (r.id === dialog.rider.id ? { ...r, ...balances } : r)))}
        />
      )}

      {current && (
        <>
          <ConfirmDialog
            open={dialog?.type === 'sign-out'}
            onOpenChange={(open) => !open && setDialog(null)}
            title={`Sign ${current.name} out?`}
            desc='Use this for a lost or changed phone. The app on that phone stops working right away. Send a new app link or PIN to sign in again.'
            confirmText='Sign out'
            destructive
            isLoading={busy}
            handleConfirm={() => runAction(() => axios.post(`/api/riders/${current.id}/sign-out`))}
          />
          <ConfirmDialog
            open={dialog?.type === 'suspend'}
            onOpenChange={(open) => !open && setDialog(null)}
            title={current.status === 'active' ? `Suspend ${current.name}?` : `Reactivate ${current.name}?`}
            desc={
              current.status === 'active'
                ? 'The rider app stops working immediately. Parcels they hold stay assigned to them.'
                : 'The rider can use the app again on the phone they were signed in on.'
            }
            confirmText={current.status === 'active' ? 'Suspend' : 'Reactivate'}
            destructive={current.status === 'active'}
            isLoading={busy}
            handleConfirm={() =>
              runAction(() =>
                axios.post(`/api/riders/${current.id}/status`, { status: current.status === 'active' ? 'suspended' : 'active' })
              )
            }
          />
          <ConfirmDialog
            open={dialog?.type === 'delete'}
            onOpenChange={(open) => !open && setDialog(null)}
            title={`Remove ${current.name}?`}
            desc='They are signed out and moved to the recycle bin, where they can be restored. Their delivery history is kept.'
            confirmText='Remove'
            destructive
            isLoading={busy}
            handleConfirm={() => runAction(() => axios.delete(`/api/riders/${current.id}`), current.id)}
          />
        </>
      )}
    </>
  )
}
