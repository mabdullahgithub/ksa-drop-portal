import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { DataTableColumnHeader } from '@/components/data-table'
import { type Product } from '@/types/product'
import { InventoryRowActions } from './inventory-row-actions'
import { format } from 'date-fns'

const statusColorMap: Record<string, string> = {
  active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  draft: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  archived: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
}

const stockColorMap = (qty: number) => {
  if (qty === 0) return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
  if (qty <= 5) return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
  return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
}

export const inventoryColumns: ColumnDef<Product>[] = [
  {
    id: 'select',
    header: ({ table }) => (
      <Checkbox
        checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
        aria-label='Select all'
        className='translate-y-0.5'
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label='Select row'
        className='translate-y-0.5'
      />
    ),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'image',
    header: () => <span className='text-xs font-medium'>Image</span>,
    cell: ({ row }) => {
      const img = row.original.primary_image
      return img ? (
        <div className='h-9 w-9 rounded-md overflow-hidden border border-muted/50 bg-muted/20 shrink-0'>
          <img src={img} alt={row.original.title} className='h-full w-full object-cover' />
        </div>
      ) : (
        <div className='h-9 w-9 rounded-md border border-dashed border-muted-foreground/30 bg-muted/20 flex items-center justify-center shrink-0'>
          <span className='text-[8px] text-muted-foreground/40 font-medium'>IMG</span>
        </div>
      )
    },
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'title',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Product' />,
    meta: { className: 'ps-1 max-w-0 w-2/5', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const product = row.original
      return (
        <div className='flex flex-col gap-0 min-w-0'>
          <span className='truncate font-medium text-xs'>{product.title}</span>
          {product.variant_sku && (
            <span className='text-[10px] text-muted-foreground truncate'>SKU: {product.variant_sku}</span>
          )}
        </div>
      )
    },
    enableSorting: true,
    enableHiding: false,
  },
  {
    accessorKey: 'vendor',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Vendor' />,
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => (
      <span className='text-[11px] text-muted-foreground truncate max-w-[120px] block'>
        {row.getValue('vendor') || '-'}
      </span>
    ),
    enableHiding: true,
  },
  {
    accessorKey: 'variant_price',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Price' />,
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const price = row.original.variant_price
      const compare = row.original.variant_compare_at_price
      return (
        <div className='flex flex-col gap-0'>
          <span className='font-semibold text-xs'>
            {price ? `SAR ${parseFloat(price).toFixed(2)}` : '-'}
          </span>
          {compare && parseFloat(compare) > 0 && parseFloat(compare) !== parseFloat(price || '0') && (
            <span className='text-[10px] text-muted-foreground line-through'>
              SAR {parseFloat(compare).toFixed(2)}
            </span>
          )}
        </div>
      )
    },
    enableSorting: true,
  },
  {
    accessorKey: 'variant_inventory_qty',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Stock' />,
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const qty = row.getValue('variant_inventory_qty') as number
      return (
        <Badge variant='secondary' className={`text-[10px] py-0 h-4 px-1.5 font-medium tabular-nums ${stockColorMap(qty)}`}>
          {qty}
        </Badge>
      )
    },
    enableSorting: true,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Status' />,
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const status = row.getValue('status') as string
      return (
        <Badge variant='secondary' className={`capitalize text-[10px] py-0 h-4 px-1.5 ${statusColorMap[status] || ''}`}>
          {status}
        </Badge>
      )
    },
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Added' />,
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => (
      <span className='text-[11px] text-muted-foreground'>
        {format(new Date(row.getValue('created_at')), 'MMM dd, yyyy')}
      </span>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'actions',
    cell: ({ row }) => <InventoryRowActions row={row} />,
  },
]
