import { type ColumnDef } from '@tanstack/react-table'
import { format } from 'date-fns'
import { DataTableColumnHeader } from '@/components/data-table'
import { type Tag } from '../data/schema'
import { DataTableRowActions } from './data-table-row-actions'

export const tagsColumns: ColumnDef<Tag>[] = [
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Name' />,
    cell: ({ row }) => {
      const tag = row.original
      return (
        <div className='flex items-center gap-2'>
          <span
            className='inline-block h-3 w-3 rounded-full flex-shrink-0'
            style={{ backgroundColor: tag.color }}
          />
          <span className='font-medium'>{tag.name}</span>
        </div>
      )
    },
    enableHiding: false,
  },
  {
    accessorKey: 'color',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Color' />,
    cell: ({ row }) => (
      <div className='flex items-center gap-2'>
        <span
          className='inline-block h-5 w-5 rounded-md border border-input'
          style={{ backgroundColor: row.getValue('color') }}
        />
        <span className='font-mono text-xs text-muted-foreground'>{row.getValue('color')}</span>
      </div>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'description',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Description' />,
    cell: ({ row }) => (
      <span className='text-sm text-muted-foreground line-clamp-1'>
        {row.getValue('description') || '—'}
      </span>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Created' />,
    cell: ({ row }) => (
      <span className='text-sm text-muted-foreground'>
        {format(new Date(row.getValue('created_at')), 'MMM d, yyyy')}
      </span>
    ),
    enableSorting: false,
  },
  {
    id: 'actions',
    cell: DataTableRowActions,
  },
]
