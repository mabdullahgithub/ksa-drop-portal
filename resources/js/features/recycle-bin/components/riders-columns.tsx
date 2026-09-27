import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { type TrashedRider } from '@/hooks/useRecycleBin'
import { DeletedAtCell } from './deleted-at-cell'
import { DeletedByCell } from './deleted-by-cell'
import { selectColumn } from './select-column'

export const trashedRidersColumns: ColumnDef<TrashedRider>[] = [
  selectColumn<TrashedRider>(),
  {
    accessorKey: 'name',
    header: () => <span className='text-sm font-medium'>Rider</span>,
    cell: ({ row }) => (
      <div className='flex flex-col'>
        <span className='font-medium'>{row.original.name}</span>
        {row.original.name_ar && (
          <span dir='rtl' className='text-muted-foreground text-xs'>
            {row.original.name_ar}
          </span>
        )}
      </div>
    ),
  },
  {
    id: 'phone',
    header: () => <span className='text-sm font-medium'>Phone</span>,
    cell: ({ row }) => <span className='text-muted-foreground tabular-nums'>{row.original.phone_local}</span>,
  },
  {
    id: 'hub',
    header: () => <span className='text-sm font-medium'>Hub</span>,
    cell: ({ row }) =>
      row.original.warehouse_name || row.original.city || <span className='text-muted-foreground'>—</span>,
  },
  {
    id: 'status',
    header: () => <span className='text-sm font-medium'>Status</span>,
    cell: ({ row }) => (
      <Badge variant='outline' className='capitalize'>
        {row.original.status}
      </Badge>
    ),
  },
  {
    // Any history blocks a permanent delete (it would erase who delivered
    // what), so show it before the purge is confirmed.
    id: 'parcels',
    header: () => <span className='text-sm font-medium'>History</span>,
    cell: ({ row }) => (
      <span className='text-muted-foreground whitespace-nowrap'>
        {row.original.parcels_count} {row.original.parcels_count === 1 ? 'parcel' : 'parcels'}
      </span>
    ),
  },
  {
    accessorKey: 'deleted_at',
    header: () => <span className='text-sm font-medium'>Deleted</span>,
    cell: ({ row }) => <DeletedAtCell value={row.original.deleted_at} />,
  },
  {
    id: 'deleted_by',
    header: () => <span className='text-sm font-medium'>Deleted by</span>,
    cell: ({ row }) => <DeletedByCell info={row.original.deleted_by} />,
  },
]
