import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { Activity, LayoutGrid, Plus, Search as SearchIcon, Smartphone, Table2, Warehouse, X } from 'lucide-react'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { usePermissions } from '@/hooks/use-permissions'
import { cn } from '@/lib/utils'
import { RiderAccessDialog } from './components/rider-access-dialog'
import { RiderCard } from './components/rider-card'
import { RiderFormDialog } from './components/rider-form-dialog'
import { RiderSupportButton } from './components/rider-support-button'
import { AppState, appStateOf, RiderAvatar, RowActions, sar, type RiderDialog } from './components/rider-parts'
import type { RiderRow, RiderSupportContact, WarehouseOption } from './data/types'

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

const NO_HUB = 'none'

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
  const [appStates, setAppStates] = useState<string[]>([])
  const [hubs, setHubs] = useState<string[]>([])
  const [dialog, setDialog] = useState<RiderDialog | null>(null)
  const [busy, setBusy] = useState(false)

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
      if (appStates.length && !appStates.some((s) => (s === 'online' ? r.online : s === appStateOf(r)))) return false
      if (hubs.length && !hubs.includes(r.warehouse_id ? String(r.warehouse_id) : NO_HUB)) return false
      if (!q) return true
      return [r.name, r.name_ar, r.phone, r.phone_local, r.city, r.warehouse_name, r.vehicle_plate]
        .filter(Boolean)
        .some((v) => String(v).toLowerCase().includes(q))
    })
  }, [riders, query, statuses, appStates, hubs])

  const filtering = query.trim() !== '' || statuses.length > 0 || appStates.length > 0 || hubs.length > 0
  const clearFilters = () => {
    setQuery('')
    setStatuses([])
    setAppStates([])
    setHubs([])
  }

  const totals = useMemo(
    () => ({
      active: riders.filter((r) => r.status === 'active').length,
      signedIn: riders.filter((r) => r.device).length,
      online: riders.filter((r) => r.online).length,
      held: riders.reduce((sum, r) => sum + r.stats.held, 0),
      delivered: riders.reduce((sum, r) => sum + r.stats.delivered, 0),
      cash: riders.reduce((sum, r) => sum + r.stats.cod_collected, 0),
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
            <p className='text-muted-foreground'>KSA Express riders and the app on their phones.</p>
          </div>
          <div className='flex items-center gap-2'>
            <RiderSupportButton support={support} canManage={canManage} onChange={setSupport} />
            <Can permission='manage riders'>
              <Button onClick={() => setDialog({ type: 'add' })}>
                <Plus size={16} className='me-1' />
                Add rider
              </Button>
            </Can>
          </div>
        </div>

        <div className='grid grid-cols-2 gap-3 md:grid-cols-6'>
          <Summary label='Active riders' value={totals.active} />
          <Summary label='Online now' value={totals.online} dot />
          <Summary label='Signed in to the app' value={totals.signedIn} />
          <Summary label='Parcels with riders' value={totals.held} />
          <Summary label='Delivered today' value={totals.delivered} />
          <Summary label='Cash collected today' value={sar(totals.cash)} />
        </div>

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
                              <div className='font-medium'>{rider.name}</div>
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
                        <TableCell className='text-end tabular-nums'>{rider.stats.held}</TableCell>
                        <TableCell className='hidden text-end text-sm tabular-nums md:table-cell'>
                          <span className='text-green-700 dark:text-green-400'>{rider.stats.delivered} delivered</span>
                          {rider.stats.failed > 0 && <span className='text-red-600'> · {rider.stats.failed} failed</span>}
                        </TableCell>
                        <TableCell className='hidden text-end tabular-nums lg:table-cell'>{sar(rider.stats.cod_collected)}</TableCell>
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

function Summary({ label, value, dot, className }: { label: string; value: number | string; dot?: boolean; className?: string }) {
  return (
    <div className={cn('rounded-lg border p-3', className)}>
      <p className='flex items-center gap-1.5 text-xs text-muted-foreground'>
        {dot && <span className='size-2 rounded-full bg-green-500' />}
        {label}
      </p>
      <p className='mt-1 text-xl font-semibold tabular-nums'>{value}</p>
    </div>
  )
}
