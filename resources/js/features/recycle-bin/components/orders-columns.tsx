import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { type TrashedOrder } from '@/hooks/useRecycleBin'
import { DeletedAtCell } from './deleted-at-cell'
import { DeletedByCell } from './deleted-by-cell'
import { selectColumn } from './select-column'

export const trashedOrdersColumns: ColumnDef<TrashedOrder>[] = [
  selectColumn<TrashedOrder>(),
  {
    accessorKey: 'order_number',
    header: () => <span className='text-sm font-medium'>Order</span>,
    cell: ({ row }) => <span className='font-medium'>{row.original.order_number}</span>,
  },
  {
    accessorKey: 'customer_name',
    header: () => <span className='text-sm font-medium'>Customer</span>,
    cell: ({ row }) => row.original.customer_name || <span className='text-muted-foreground'>—</span>,
  },
  {
    accessorKey: 'client_name',
    header: () => <span className='text-sm font-medium'>Client</span>,
    cell: ({ row }) => {
      const { client_name, client_trashed } = row.original
      if (!client_name) return <span className='text-muted-foreground'>—</span>

      return (
        <span className='flex items-center gap-1.5'>
          {client_name}
          {/* The client itself is in the bin; restoring this order is still
              fine, the order just points at a deleted client. */}
          {client_trashed && (
            <Badge variant='outline' className='text-[10px] text-muted-foreground'>
              deleted
            </Badge>
          )}
        </span>
      )
    },
  },
  {
    accessorKey: 'items_count',
    header: () => <span className='text-sm font-medium'>Items</span>,
    cell: ({ row }) => row.original.items_count,
  },
  {
    accessorKey: 'total_price',
    header: () => <span className='text-sm font-medium'>Total</span>,
    cell: ({ row }) => {
      const total = row.original.total_price
      return total === null ? <span className='text-muted-foreground'>—</span> : `SAR ${Number(total).toFixed(2)}`
    },
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
