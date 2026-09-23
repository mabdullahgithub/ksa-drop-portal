import { type ColumnDef } from '@tanstack/react-table'
import { type TrashedClient } from '@/hooks/useRecycleBin'
import { DeletedAtCell } from './deleted-at-cell'
import { DeletedByCell } from './deleted-by-cell'
import { selectColumn } from './select-column'

export const trashedClientsColumns: ColumnDef<TrashedClient>[] = [
  selectColumn<TrashedClient>(),
  {
    accessorKey: 'company_name',
    header: () => <span className='text-sm font-medium'>Company</span>,
    cell: ({ row }) => <span className='font-medium'>{row.original.company_name}</span>,
  },
  {
    accessorKey: 'short_id',
    header: () => <span className='text-sm font-medium'>Client ID</span>,
    cell: ({ row }) => row.original.short_id || <span className='text-muted-foreground'>—</span>,
  },
  {
    accessorKey: 'contact_person',
    header: () => <span className='text-sm font-medium'>Contact</span>,
    cell: ({ row }) => row.original.contact_person || <span className='text-muted-foreground'>—</span>,
  },
  {
    // Deleting a client never cascades, so these still exist and are still
    // visible elsewhere in the portal. Shown so the cost of a permanent
    // delete is legible before it is confirmed.
    id: 'attached',
    header: () => <span className='text-sm font-medium'>Attached</span>,
    cell: ({ row }) => (
      <span className='text-muted-foreground whitespace-nowrap'>
        {row.original.orders_count} orders · {row.original.products_count} products
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
