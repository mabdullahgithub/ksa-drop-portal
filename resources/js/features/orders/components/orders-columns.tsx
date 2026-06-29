import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { DataTableColumnHeader } from '@/components/data-table'
import { type Order } from '@/types/order'
import { DataTableRowActions } from './data-table-row-actions'
import { format } from 'date-fns'
import { useAvailableTags } from '@/hooks/useTags'

function hexToRgba(hex: string | null | undefined, alpha: number) {
  if (!hex || hex.length < 4) return `rgba(128, 128, 128, ${alpha})`
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function TagsCell({ tags }: { tags: string[] | null }) {
  const { tags: predefined } = useAvailableTags()
  if (!tags || tags.length === 0) return <span className='text-xs text-muted-foreground'>—</span>
  return (
    <div className='flex flex-wrap gap-1'>
      {tags.map((name) => {
        const meta = predefined.find((t) => t.name === name)
        return meta ? (
          <span
            key={name}
            className='inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium'
            style={{
              backgroundColor: hexToRgba(meta.color, 0.15),
              color: meta.color,
              border: `1px solid ${hexToRgba(meta.color, 0.3)}`,
            }}
          >
            {name}
          </span>
        ) : (
          <Badge key={name} variant='secondary' className='text-[10px] py-0 px-1.5 h-4'>
            {name}
          </Badge>
        )
      })}
    </div>
  )
}

const statusColorMap: Record<string, string> = {
  success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  default: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
}

const shipmentColorMap: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
  blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
  orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
  red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
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
    id: 'client',
    accessorFn: (row) => row.client?.company_name ?? null,
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Client' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const client = row.original.client
      if (!client) return <span className='text-xs text-muted-foreground'>—</span>
      return (
        <div className='flex flex-col gap-0'>
          <span className='truncate text-sm font-medium'>{client.company_name}</span>
          <span className='text-xs text-muted-foreground'>{client.client_id}</span>
        </div>
      )
    },
    enableHiding: true,
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
      return <span className='truncate font-medium text-sm'>{name || 'N/A'}</span>
    },
  },
  {
    accessorKey: 'customer_phone',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Phone' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const phone = row.getValue('customer_phone') as string | null
      return (
        <span className='text-sm text-muted-foreground' dir='ltr'>
          {phone || '—'}
        </span>
      )
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'customer_email',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Email' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const email = row.getValue('customer_email') as string | null
      return (
        <span className='text-sm text-muted-foreground truncate max-w-[180px] block'>
          {email || '—'}
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
        <span className='font-semibold text-sm'>
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
        <Badge variant='outline' className={`capitalize text-xs py-0 h-5 px-1.5 ${statusColorMap[color] || statusColorMap.default}`}>
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
        <Badge variant='outline' className={`capitalize text-xs py-0 h-5 px-1.5 ${statusColorMap[color] || statusColorMap.default}`}>
          {status}
        </Badge>
      )
    },
    filterFn: (row, id, value) => {
      return value.includes(row.getValue(id))
    },
  },
  {
    id: 'shipment',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Shipment' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => {
      const shipment = row.original.latest_shipment
      if (!shipment) {
        return <span className='text-xs text-muted-foreground'>—</span>
      }
      return (
        <Badge
          variant='outline'
          className={`text-xs py-0 h-5 px-1.5 ${shipmentColorMap[shipment.status_color] || shipmentColorMap.gray}`}
        >
          {shipment.status_label}
        </Badge>
      )
    },
    enableSorting: false,
    enableHiding: true,
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
        <span className='truncate max-w-[120px] block text-sm'>
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
        <span className='text-sm'>
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
        <span className='text-sm text-muted-foreground'>
          {format(new Date(date), 'MMM dd, yyyy')}
        </span>
      )
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'tags',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Tags' />
    ),
    meta: { className: 'ps-1', tdClassName: 'ps-4' },
    cell: ({ row }) => <TagsCell tags={row.original.tags} />,
    enableSorting: false,
    enableHiding: true,
  },
  {
    id: 'actions',
    header: () => <span className='text-sm font-medium'>Actions</span>,
    cell: DataTableRowActions,
  },
]
