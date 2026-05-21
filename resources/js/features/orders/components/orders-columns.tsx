import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { DataTableColumnHeader } from '@/components/data-table'
import { type Order } from '@/types/order'
import { DataTableRowActions } from './data-table-row-actions'
import { format } from 'date-fns'

const statusColorMap: Record<string, string> = {
  success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  default: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
}

export const ordersColumns: ColumnDef<Order>[] = [
  {
    id: 'select',
    header: ({ table }) => (
      <Checkbox
        checked={
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && 'indeterminate')
        }
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
    accessorKey: 'order_number',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Order #' />
    ),
    cell: ({ row }) => (
      <div className='w-20 font-medium'>{row.getValue('order_number')}</div>
    ),
    enableSorting: true,
    enableHiding: false,
  },
  {
    accessorKey: 'customer_name',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Customer' />
    ),
    meta: {
      className: 'ps-1 max-w-0 w-1/4',
      tdClassName: 'ps-4',
    },
    cell: ({ row }) => {
      const name = row.getValue('customer_name') as string | null
      const phone = row.original.customer_phone

      return (
        <div className='flex flex-col gap-0'>
          <span className='truncate font-medium'>{name || 'N/A'}</span>
          {phone && (
            <span className='text-[10px] text-muted-foreground truncate' dir='ltr'>
              {phone}
            </span>
          )}
        </div>
      )
    },
  },
  {
    accessorKey: 'customer_email',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Email' />
    ),
    meta: {
      className: 'ps-1',
      tdClassName: 'ps-4',
    },
    cell: ({ row }) => {
      const email = row.getValue('customer_email') as string | null
      return (
        <span className='text-muted-foreground truncate max-w-[180px] block'>
          {email || '-'}
        </span>
      )
    },
    enableHiding: true,
  },
  {
    accessorKey: 'total',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Total' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const total = parseFloat(row.original.total)
      const currency = row.original.currency
      return (
        <span className='font-semibold'>
          {currency} {total.toFixed(2)}
        </span>
      )
    },
    enableSorting: true,
  },
  {
    accessorKey: 'fulfillment_status',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Fulfillment' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const status = row.getValue('fulfillment_status') as string
      const color = row.original.status_color

      return (
        <Badge variant='secondary' className={`capitalize text-[10px] py-0 h-4 px-1.5 ${statusColorMap[color] || statusColorMap.default}`}>
          {status}
        </Badge>
      )
    },
    filterFn: (row, id, value) => {
      return value.includes(row.getValue(id))
    },
  },
  {
    accessorKey: 'financial_status',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Payment' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const status = row.getValue('financial_status') as string
      const color = row.original.financial_status_color

      return (
        <Badge variant='secondary' className={`capitalize text-[10px] py-0 h-4 px-1.5 ${statusColorMap[color] || statusColorMap.default}`}>
          {status}
        </Badge>
      )
    },
    filterFn: (row, id, value) => {
      return value.includes(row.getValue(id))
    },
  },
  {
    accessorKey: 'payment_method',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Method' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const method = row.getValue('payment_method') as string | null
      return (
        <span className='truncate max-w-[120px] block text-[11px]'>
          {method || 'N/A'}
        </span>
      )
    },
    enableHiding: true,
  },
  {
    accessorKey: 'shipping_country',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Country' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const country = row.getValue('shipping_country') as string | null
      return (
        <span className='text-[11px]'>
          {country || '-'}
        </span>
      )
    },
    enableHiding: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Date' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const date = row.getValue('created_at') as string
      return (
        <span className='text-[11px] text-muted-foreground'>
          {format(new Date(date), 'MMM dd, yyyy')}
        </span>
      )
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'actions',
    cell: ({ row }) => <DataTableRowActions row={row} />,
  },
]
