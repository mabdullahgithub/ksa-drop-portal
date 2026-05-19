import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { Trash2, UserPen } from 'lucide-react'
import { usePage } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { type User } from '../data/schema'
import { useUsers } from './users-provider'
import { usePermissions } from '@/hooks/use-permissions'

type DataTableRowActionsProps = {
  row: Row<User>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useUsers()
  const { auth } = usePage<{ auth?: { user?: { id: number } } }>().props
  const { can } = usePermissions()

  // Check if this is real Laravel data
  const isRealData = row.original && typeof row.original.id === 'number'
  const isSuperAdmin = isRealData && 'is_super_admin' in row.original && (row.original as any).is_super_admin
  const isSelf = isRealData && auth?.user && row.original.id === auth.user.id
  const canDelete = !isSuperAdmin && !isSelf && can('delete users')

  // Check permissions
  const canEdit = can('edit users')
  const canDeleteUsers = can('delete users')

  // Don't show menu if user has no permissions
  if (!canEdit && !canDeleteUsers) {
    return null
  }

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button
            variant='ghost'
            className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
          >
            <DotsHorizontalIcon className='h-4 w-4' />
            <span className='sr-only'>Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align='end' className='w-40'>
          {canEdit && (
            <DropdownMenuItem
              onClick={() => {
                setCurrentRow(row.original)
                setOpen('edit')
              }}
            >
              Edit
              <DropdownMenuShortcut>
                <UserPen size={16} />
              </DropdownMenuShortcut>
            </DropdownMenuItem>
          )}
          {canEdit && canDeleteUsers && <DropdownMenuSeparator />}
          {canDeleteUsers && (
            <DropdownMenuItem
              onClick={() => {
                setCurrentRow(row.original)
                setOpen('delete')
              }}
              disabled={!canDelete}
              className='text-red-500!'
            >
              Delete
              <DropdownMenuShortcut>
                <Trash2 size={16} />
              </DropdownMenuShortcut>
            </DropdownMenuItem>
          )}
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  )
}
