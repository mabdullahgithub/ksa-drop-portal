import { ArrowDownToLine, ArrowUpFromLine, Bike, ChevronRight, Coins, ListChecks, Undo2, Wallet, Warehouse } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { VEHICLE_TYPES, type RiderRow } from '../data/types'
import { AppState, CashDue, PayDue, RiderAvatar, RowActions, sar, type RiderDialog } from './rider-parts'

type Props = {
  rider: RiderRow
  canManage: boolean
  onPick: (dialog: RiderDialog) => void
}

export function RiderCard({ rider, canManage, onPick }: Props) {
  const suspended = rider.status === 'suspended'
  const vehicle = VEHICLE_TYPES.find((v) => v.value === rider.vehicle_type)?.label
  // Stays at the warehouse scanning parcels OUT and IN: no parcels, cash or pay of their own.
  const isManager = rider.role === 'inventory_manager'

  return (
    <div className={cn('flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs', suspended && 'bg-muted/40')}>
      <div className='flex items-start gap-3'>
        <RiderAvatar name={rider.name} photoUrl={rider.photo_url} online={rider.online} className={cn('size-14 text-lg', suspended && 'grayscale')} />

        <div className='min-w-0 flex-1'>
          {/* The badges drop under the name rather than squeeze it. */}
          <div className='flex flex-wrap items-center gap-x-2 gap-y-1'>
            <p className='max-w-full truncate font-semibold'>{rider.name}</p>
            {isManager && (
              <Badge variant='outline' className='shrink-0 border-blue-300 text-blue-700 dark:text-blue-400'>
                Inventory manager
              </Badge>
            )}
            {suspended && (
              <Badge variant='outline' className='border-red-300 text-red-700 dark:text-red-400'>
                Suspended
              </Badge>
            )}
          </div>
          {rider.name_ar && (
            <p className='truncate text-sm text-muted-foreground'>
              <bdi>{rider.name_ar}</bdi>
            </p>
          )}
          <p className='text-sm text-muted-foreground' dir='ltr'>
            {rider.phone_local}
          </p>
        </div>

        {canManage && <RowActions rider={rider} onPick={onPick} />}
      </div>

      <div className='flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground'>
        {rider.warehouse_name && (
          <span className='inline-flex items-center gap-1'>
            <Warehouse className='h-3.5 w-3.5' />
            {rider.warehouse_name}
          </span>
        )}
        {!isManager && (vehicle || rider.vehicle_plate) && (
          <span className='inline-flex items-center gap-1'>
            <Bike className='h-3.5 w-3.5' />
            {[vehicle, rider.vehicle_plate].filter(Boolean).join(' · ')}
          </span>
        )}
        {isManager ? null : rider.delivery_rate !== null || rider.attempt_rate !== null ? (
          <span className='inline-flex items-center gap-1' title='What KSA Drop pays this rider per delivery / per attempt'>
            <Coins className='h-3.5 w-3.5' />
            SAR {rate(rider.delivery_rate)} / {rate(rider.attempt_rate)}
          </span>
        ) : (
          <span className='inline-flex items-center gap-1 text-amber-700 dark:text-amber-400' title='Set this rider’s pay per delivery and per attempt in their details'>
            <Coins className='h-3.5 w-3.5' />
            No pay rates
          </span>
        )}
        {rider.stats.to_return > 0 && (
          <span
            className='inline-flex items-center gap-1 font-medium text-amber-700 dark:text-amber-400'
            title='Returned or cancelled parcels the rider has not handed back at the hub yet'
          >
            <Undo2 className='h-3.5 w-3.5' />
            {rider.stats.to_return} to hand back
          </span>
        )}
      </div>

      <AppState rider={rider} />

      {isManager ? (
        <StockToday rider={rider} />
      ) : (
        <RiderNumbers rider={rider} onPick={onPick} />
      )}
    </div>
  )
}

/** Parcels an inventory manager scanned today, each way, and the units in them. */
function StockToday({ rider }: { rider: RiderRow }) {
  const today = rider.stock_today

  return (
    <div className='mt-auto grid grid-cols-2 gap-1 border-t pt-2 text-center'>
      <div className='py-1'>
        <p className='text-lg font-semibold leading-6 tabular-nums'>{today?.out.parcels ?? 0}</p>
        <p className='inline-flex items-center gap-1 text-[11px] text-muted-foreground'>
          <ArrowUpFromLine className='h-3 w-3' />
          Out today · {today?.out.pieces ?? 0} pcs
        </p>
      </div>
      <div className='py-1'>
        <p className='text-lg font-semibold leading-6 tabular-nums text-green-700 dark:text-green-400'>{today?.in.parcels ?? 0}</p>
        <p className='inline-flex items-center gap-1 text-[11px] text-muted-foreground'>
          <ArrowDownToLine className='h-3 w-3' />
          In today · {today?.in.pieces ?? 0} pcs
        </p>
      </div>
    </div>
  )
}

/** A rider's parcels today and their money both ways. */
function RiderNumbers({ rider, onPick }: Pick<Props, 'rider' | 'onPick'>) {
  return (
    <>
      {/* Each number opens the rider's orders on that filter. */}
      <div className='mt-auto grid grid-cols-4 gap-1 border-t pt-2 text-center'>
        <Stat label='With rider' value={rider.stats.held} onClick={() => onPick({ type: 'orders', rider })} />
        <Stat
          label='Delivered'
          value={rider.stats.delivered}
          tone='text-green-700 dark:text-green-400'
          onClick={() => onPick({ type: 'orders', rider, outcome: 'delivered' })}
        />
        <Stat
          label='Failed'
          value={rider.stats.failed}
          tone={rider.stats.failed ? 'text-red-600' : undefined}
          onClick={() => onPick({ type: 'orders', rider, outcome: 'attempt_fail' })}
        />
        <Stat
          label='Cash today'
          value={sar(rider.stats.cash_collected).replace('SAR ', '')}
          small
          onClick={() => onPick({ type: 'orders', rider, outcome: 'delivered' })}
        />
      </div>

      {/* The rider's orders, and their money both ways. Each line opens its sheet. */}
      <div className='-mx-2 -my-1'>
        <button
          type='button'
          onClick={() => onPick({ type: 'orders', rider })}
          className='flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60'
        >
          <ListChecks className='h-3.5 w-3.5 text-muted-foreground' />
          <span className='text-muted-foreground'>Orders</span>
          <span className='ms-auto text-muted-foreground'>Delivered, failed, all</span>
          <ChevronRight className='h-3.5 w-3.5 text-muted-foreground rtl:rotate-180' />
        </button>
        <button
          type='button'
          onClick={() => onPick({ type: 'payments', rider, direction: 'in' })}
          className='flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60'
        >
          <Wallet className='h-3.5 w-3.5 text-muted-foreground' />
          <span className='text-muted-foreground'>Cash to hand in</span>
          <CashDue cash={rider.cash} className='ms-auto' />
          <ChevronRight className='h-3.5 w-3.5 text-muted-foreground rtl:rotate-180' />
        </button>
        <button
          type='button'
          onClick={() => onPick({ type: 'payments', rider, direction: 'out' })}
          className='flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60'
        >
          <Coins className='h-3.5 w-3.5 text-muted-foreground' />
          <span className='text-muted-foreground'>Pay owed to rider</span>
          <PayDue pay={rider.pay} className='ms-auto' />
          <ChevronRight className='h-3.5 w-3.5 text-muted-foreground rtl:rotate-180' />
        </button>
      </div>
    </>
  )
}

/** A rate without trailing zeros: 10, 4.5. */
const rate = (n: number | null) => (n ?? 0).toLocaleString('en-US', { maximumFractionDigits: 2 })

function Stat({
  label,
  value,
  tone,
  small,
  onClick,
}: {
  label: string
  value: number | string
  tone?: string
  small?: boolean
  onClick: () => void
}) {
  return (
    <button type='button' onClick={onClick} className='rounded-md py-1 transition-colors hover:bg-muted/60' title={`${label}: view orders`}>
      <p className={cn('font-semibold tabular-nums', small ? 'text-sm leading-6' : 'text-lg leading-6', tone)}>{value}</p>
      <p className='text-[11px] text-muted-foreground'>{label}</p>
    </button>
  )
}
