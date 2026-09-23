import { type ColumnDef } from '@tanstack/react-table'
import { AlertTriangle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { type TrashedInventoryItem } from '@/hooks/useRecycleBin'
import { DeletedAtCell } from './deleted-at-cell'
import { DeletedByCell } from './deleted-by-cell'
import { selectColumn } from './select-column'

export const trashedInventoryColumns: ColumnDef<TrashedInventoryItem>[] = [
  selectColumn<TrashedInventoryItem>(),
  {
    // "Inventory" spans two models, so every row says which one it came from.
    id: 'item_type',
    header: () => <span className='text-sm font-medium'>Type</span>,
    cell: ({ row }) =>
      row.original.item_type === 'product' ? (
        <Badge variant='secondary' className='whitespace-nowrap'>Catalog</Badge>
      ) : (
        <Badge variant='outline' className='whitespace-nowrap'>Client stock</Badge>
      ),
  },
  {
    accessorKey: 'name',
    header: () => <span className='text-sm font-medium'>Name</span>,
    cell: ({ row }) => (
      <span className='flex items-center gap-1.5'>
        <span className='font-medium'>{row.original.name || '—'}</span>
        {row.original.parent_trashed && (
          <Tooltip>
            <TooltipTrigger asChild>
              <AlertTriangle className='h-3.5 w-3.5 text-amber-500' />
            </TooltipTrigger>
            <TooltipContent>
              Its client is also deleted. Restore the client first.
            </TooltipContent>
          </Tooltip>
        )}
      </span>
    ),
  },
  {
    accessorKey: 'sku',
    header: () => <span className='text-sm font-medium'>SKU</span>,
    cell: ({ row }) => row.original.sku || <span className='text-muted-foreground'>—</span>,
  },
  {
    accessorKey: 'code',
    header: () => <span className='text-sm font-medium'>Code</span>,
    cell: ({ row }) => (
      <span className='text-muted-foreground font-mono text-[11px]'>{row.original.code || '—'}</span>
    ),
  },
  {
    accessorKey: 'client_name',
    header: () => <span className='text-sm font-medium'>Client</span>,
    cell: ({ row }) => row.original.client_name || <span className='text-muted-foreground'>—</span>,
  },
  {
    accessorKey: 'quantity',
    header: () => <span className='text-sm font-medium'>Qty</span>,
    cell: ({ row }) =>
      row.original.quantity ?? <span className='text-muted-foreground'>—</span>,
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
