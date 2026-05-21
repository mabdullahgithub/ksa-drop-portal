import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { DataTableColumnHeader } from '@/components/data-table/column-header'
import type { Client } from '@/types/client'
import { ClientRowActions } from './client-row-actions'

export const clientColumns: ColumnDef<Client>[] = [
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
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label='Select row'
      />
    ),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'client_id',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='ID' />
    ),
    cell: ({ row }) => (
      <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold'>
        {row.original.client_id}
      </code>
    ),
    enableSorting: true,
  },
  {
    accessorKey: 'company_name',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Company' />
    ),
    cell: ({ row }) => (
      <div>
        <div className='font-medium'>{row.getValue('company_name')}</div>
        {row.original.contact_person && (
          <div className='text-xs text-muted-foreground'>{row.original.contact_person}</div>
        )}
      </div>
    ),
    enableSorting: true,
  },
  {
    id: 'email',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Email' />
    ),
    cell: ({ row }) => (
      <span className='text-sm'>{row.original.user?.email ?? '—'}</span>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'client_types',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Type' />
    ),
    cell: ({ row }) => {
      const types = row.original.client_types
      return (
        <div className='flex gap-1'>
          {types.map((type) => (
            <Badge key={type} variant='outline' className='text-xs capitalize'>
              {type}
            </Badge>
          ))}
        </div>
      )
    },
    enableSorting: false,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Status' />
    ),
    cell: ({ row }) => {
      const status = row.original.status
      const colorMap = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        inactive: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        suspended: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
      }
      return (
        <Badge variant='secondary' className={`text-xs capitalize ${colorMap[status]}`}>
          {status}
        </Badge>
      )
    },
    enableSorting: true,
  },
  {
    id: 'orders_count',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Orders' />
    ),
    cell: ({ row }) => (
      <span className='text-sm'>{row.original.orders_count ?? 0}</span>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Created' />
    ),
    cell: ({ row }) => {
      const date = new Date(row.getValue('created_at'))
      return <span className='text-sm'>{date.toLocaleDateString()}</span>
    },
    enableSorting: true,
  },
  {
    id: 'actions',
    cell: ({ row }) => <ClientRowActions row={row} />,
  },
]
