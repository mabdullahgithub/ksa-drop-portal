import { Bike, Warehouse } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { VEHICLE_TYPES, type RiderRow } from '../data/types'
import { AppState, RiderAvatar, RowActions, sar, type RiderDialog } from './rider-parts'

type Props = {
  rider: RiderRow
  canManage: boolean
  onPick: (dialog: RiderDialog) => void
}

export function RiderCard({ rider, canManage, onPick }: Props) {
  const suspended = rider.status === 'suspended'
  const vehicle = VEHICLE_TYPES.find((v) => v.value === rider.vehicle_type)?.label

  return (
    <div className={cn('flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs', suspended && 'bg-muted/40')}>
      <div className='flex items-start gap-3'>
        <RiderAvatar name={rider.name} photoUrl={rider.photo_url} online={rider.online} className={cn('size-14 text-lg', suspended && 'grayscale')} />

        <div className='min-w-0 flex-1'>
          <div className='flex items-center gap-2'>
            <p className='truncate font-semibold'>{rider.name}</p>
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
        {(vehicle || rider.vehicle_plate) && (
          <span className='inline-flex items-center gap-1'>
            <Bike className='h-3.5 w-3.5' />
            {[vehicle, rider.vehicle_plate].filter(Boolean).join(' · ')}
          </span>
        )}
      </div>

      <AppState rider={rider} />

      <div className='mt-auto grid grid-cols-4 gap-2 border-t pt-3 text-center'>
        <Stat label='With rider' value={rider.stats.held} />
        <Stat label='Delivered' value={rider.stats.delivered} tone='text-green-700 dark:text-green-400' />
        <Stat label='Failed' value={rider.stats.failed} tone={rider.stats.failed ? 'text-red-600' : undefined} />
        <Stat label='Cash today' value={sar(rider.stats.cod_collected).replace('SAR ', '')} small />
      </div>
    </div>
  )
}

function Stat({ label, value, tone, small }: { label: string; value: number | string; tone?: string; small?: boolean }) {
  return (
    <div>
      <p className={cn('font-semibold tabular-nums', small ? 'text-sm leading-6' : 'text-lg leading-6', tone)}>{value}</p>
      <p className='text-[11px] text-muted-foreground'>{label}</p>
    </div>
  )
}
