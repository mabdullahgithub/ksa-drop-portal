import { type ColumnDef } from '@tanstack/react-table'
import { Shield } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { DataTableColumnHeader } from '@/components/data-table'
import { DataTableRowActions } from './data-table-row-actions'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

interface SimpleUser {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin?: boolean
  created_at: string
}

export const usersColumnsSimple: ColumnDef<SimpleUser>[] = [
  {
    id: 'select',
    header: ({ table }) => {
      // Get all non-superadmin rows
      const selectableRows = table.getRowModel().rows.filter(
        (row) => !row.original.is_super_admin
      )
      const allSelectableSelected = selectableRows.length > 0 &&
        selectableRows.every((row) => row.getIsSelected())
      const someSelectableSelected = selectableRows.some((row) => row.getIsSelected())

      return (
        <Checkbox
          checked={allSelectableSelected || (someSelectableSelected && 'indeterminate')}
          onCheckedChange={(value) => {
            // Only toggle selectable rows
            selectableRows.forEach((row) => row.toggleSelected(!!value))
          }}
          aria-label='Select all'
          className='translate-y-0.5'
        />
      )
    },
    meta: {
      className: cn('inset-s-0 z-10 rounded-tl-[inherit] max-md:sticky'),
    },
    cell: ({ row }) => {
      const isSuperAdmin = row.original.is_super_admin
      return (
        <Checkbox
          checked={row.getIsSelected()}
          onCheckedChange={(value) => row.toggleSelected(!!value)}
          aria-label='Select row'
          className='translate-y-0.5'
          disabled={isSuperAdmin}
        />
      )
    },
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Name' />
    ),
    cell: ({ row }) => {
      const isSuperAdmin = row.original.is_super_admin
      return (
        <div className='flex items-center gap-2'>
          <span className='font-medium'>{row.getValue('name')}</span>
          {isSuperAdmin && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Shield className='h-4 w-4 text-amber-500' />
              </TooltipTrigger>
              <TooltipContent>
                <p>Protected - Cannot be modified or deleted</p>
              </TooltipContent>
            </Tooltip>
          )}
        </div>
      )
    },
    enableHiding: false,
  },
  {
    accessorKey: 'email',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Email' />
    ),
    cell: ({ row }) => (
      <div className='w-fit ps-2'>{row.getValue('email')}</div>
    ),
  },
  {
    accessorKey: 'roles',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Roles' />
    ),
    cell: ({ row }) => {
      const roles = row.getValue('roles') as string[]
      return (
        <div className='flex flex-wrap gap-1'>
          {roles.length === 0 ? (
            <span className='text-muted-foreground text-sm'>No roles</span>
          ) : (
            roles.map((role) => (
              <Badge key={role} variant='secondary' className='capitalize'>
                {role}
              </Badge>
            ))
          )}
        </div>
      )
    },
    enableSorting: false,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title='Created' />
    ),
    cell: ({ row }) => {
      const date = new Date(row.getValue('created_at'))
      return <div className='text-sm'>{date.toLocaleDateString()}</div>
    },
  },
  {
    id: 'actions',
    cell: DataTableRowActions,
  },
]
