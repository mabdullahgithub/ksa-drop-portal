import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { type TrashedUser } from '@/hooks/useRecycleBin'
import { DeletedAtCell } from './deleted-at-cell'
import { DeletedByCell } from './deleted-by-cell'
import { selectColumn } from './select-column'

export const trashedUsersColumns: ColumnDef<TrashedUser>[] = [
  selectColumn<TrashedUser>(),
  {
    accessorKey: 'name',
    header: () => <span className='text-sm font-medium'>Name</span>,
    cell: ({ row }) => <span className='font-medium'>{row.original.name}</span>,
  },
  {
    accessorKey: 'email',
    header: () => <span className='text-sm font-medium'>Email</span>,
    cell: ({ row }) => <span className='text-muted-foreground'>{row.original.email}</span>,
  },
  {
    id: 'type',
    header: () => <span className='text-sm font-medium'>Type</span>,
    cell: ({ row }) => (
      <Badge variant='outline'>{row.original.is_client ? 'Client' : 'Team'}</Badge>
    ),
  },
  {
    id: 'roles',
    header: () => <span className='text-sm font-medium'>Roles</span>,
    cell: ({ row }) =>
      row.original.roles.length ? (
        <span className='capitalize'>{row.original.roles.join(', ')}</span>
      ) : (
        <span className='text-muted-foreground'>—</span>
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
