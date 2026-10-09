import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Edit, MoreVertical, Shield, Trash } from 'lucide-react'
import { type Role } from '../data/schema'
import { useRoles } from './roles-provider'
import { usePermissions } from '@/hooks/use-permissions'

export function RoleRowActions({ role }: { role: Role }) {
  const { setOpen, setCurrentRow } = useRoles()
  const { can } = usePermissions()

  const openFor = (dialog: 'edit' | 'delete' | 'view-permissions') => {
    setCurrentRow(role)
    setOpen(dialog)
  }

  // No menu when none of its items would show.
  if (
    !can('view role permissions') &&
    (role.is_protected || !can(['edit roles', 'delete roles']))
  ) {
    return null
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' size='icon' className='h-8 w-8'>
          <MoreVertical className='h-4 w-4' />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end'>
        {can('view role permissions') && (
          <DropdownMenuItem onClick={() => openFor('view-permissions')}>
            <Shield className='mr-2 h-4 w-4' />
            View Permissions
          </DropdownMenuItem>
        )}
        {!role.is_protected && (
          <>
            {can('edit roles') && (
              <DropdownMenuItem onClick={() => openFor('edit')}>
                <Edit className='mr-2 h-4 w-4' />
                Edit
              </DropdownMenuItem>
            )}
            {can('delete roles') && (
              <DropdownMenuItem
                onClick={() => openFor('delete')}
                className='text-destructive'
              >
                <Trash className='mr-2 h-4 w-4' />
                Delete
              </DropdownMenuItem>
            )}
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
