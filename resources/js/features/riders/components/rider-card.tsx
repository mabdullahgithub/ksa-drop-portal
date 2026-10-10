import { ArrowDownToLine, ArrowUpFromLine, Bike, ChevronRight, Coins, ListChecks, ScanLine, Undo2, Wallet, Warehouse } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { VEHICLE_TYPES, type RiderRow } from '../data/types'
import { AppState, CashDue, PayDue, RiderAvatar, RowActions, sar, type RiderDialog } from './rider-parts'
import { usePermissions } from '@/hooks/use-permissions'

type Props = {
  rider: RiderRow
  onPick: (dialog: RiderDialog) => void
}

export function RiderCard({ rider, onPick }: Props) {
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

        <RowActions rider={rider} onPick={onPick} />
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
        <StockToday rider={rider} onPick={onPick} />
      ) : (
        <RiderNumbers rider={rider} onPick={onPick} />
      )}
    </div>
  )
}

/** Parcels an inventory manager scanned today, each way, and the units in them. */
function StockToday({ rider, onPick }: Pick<Props, 'rider' | 'onPick'>) {
  // Their scans are a permission of their own.
  const { can } = usePermissions()
  const canScans = can('view inventory manager scans')
  const today = rider.stock_today

  const ways = [
    { way: 'out', label: 'Out today', Icon: ArrowUpFromLine, tone: undefined },
    { way: 'in', label: 'In today', Icon: ArrowDownToLine, tone: 'text-green-700 dark:text-green-400' },
  ] as const

  return (
    <>
      {/* Each number opens today's scans that way. */}
      <div className='mt-auto grid grid-cols-2 gap-1 border-t pt-2 text-center'>
        {ways.map(({ way, label, Icon, tone }) => {
          const content = (
            <>
              <p className={cn('text-lg font-semibold leading-6 tabular-nums', tone)}>{today?.[way].parcels ?? 0}</p>
              <p className='inline-flex items-center gap-1 text-[11px] text-muted-foreground'>
                <Icon className='h-3 w-3' />
                {label} · {today?.[way].pieces ?? 0} pcs
              </p>
            </>
          )

          return canScans ? (
            <button
              key={way}
              type='button'
              onClick={() => onPick({ type: 'scans', rider, filter: way, today: true })}
              className='rounded-md py-1 transition-colors hover:bg-muted/60'
              title={`${label}: view scans`}
            >
              {content}
            </button>
          ) : (
            <div key={way} className='py-1'>
              {content}
            </div>
          )
        })}
      </div>

      {canScans && (
        <div className='-mx-2 -my-1'>
          <button
            type='button'
            onClick={() => onPick({ type: 'scans', rider })}
            className='flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60'
          >
            <ScanLine className='h-3.5 w-3.5 text-muted-foreground' />
            <span className='text-muted-foreground'>Scans</span>
            <span className='ms-auto text-muted-foreground'>Out, in, not linked</span>
            <ChevronRight className='h-3.5 w-3.5 text-muted-foreground rtl:rotate-180' />
          </button>
        </div>
      )}
    </>
  )
}

/** A rider's parcels today and their money both ways. */
function RiderNumbers({ rider, onPick }: Pick<Props, 'rider' | 'onPick'>) {
  // The orders sheet and the money are each a permission of their own.
  const { can } = usePermissions()
  const canParcels = can('view rider parcels')
  const canMoney = can('view rider payments')
  const orders = (outcome?: 'delivered' | 'attempt_fail') =>
    canParcels ? () => onPick({ type: 'orders', rider, outcome }) : undefined

  return (
    <>
      {/* Each number opens the rider's orders on that filter. */}
      <div className={cn('mt-auto grid gap-1 border-t pt-2 text-center', rider.cash ? 'grid-cols-4' : 'grid-cols-3')}>
        <Stat label='With rider' value={rider.stats.held} onClick={orders()} />
        <Stat
          label='Delivered'
          value={rider.stats.delivered}
          tone='text-green-700 dark:text-green-400'
          onClick={orders('delivered')}
        />
        <Stat
          label='Failed'
          value={rider.stats.failed}
          tone={rider.stats.failed ? 'text-red-600' : undefined}
          onClick={orders('attempt_fail')}
        />
        {rider.cash && (
          <Stat
            label='Cash today'
            value={sar(rider.stats.cash_collected).replace('SAR ', '')}
            small
            onClick={orders('delivered')}
          />
        )}
      </div>

      {/* The rider's orders, and their money both ways. Each line opens its sheet. */}
      <div className='-mx-2 -my-1'>
        {canParcels && (
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
        )}
        {canMoney && rider.cash && (
          <>
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
          </>
        )}
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
  /** Left out for someone who may not open the rider's orders: a plain number. */
  onClick?: () => void
}) {
  const content = (
    <>
      <p className={cn('font-semibold tabular-nums', small ? 'text-sm leading-6' : 'text-lg leading-6', tone)}>{value}</p>
      <p className='text-[11px] text-muted-foreground'>{label}</p>
    </>
  )

  if (!onClick) return <div className='py-1'>{content}</div>

  return (
    <button type='button' onClick={onClick} className='rounded-md py-1 transition-colors hover:bg-muted/60' title={`${label}: view orders`}>
      {content}
    </button>
  )
}
