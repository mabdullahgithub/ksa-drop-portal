import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
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

interface RolesTableProps {
  data: Role[]
}

export function RolesTable({ data }: RolesTableProps) {
  const { setOpen, setCurrentRow } = useRoles()
  const { can } = usePermissions()

  const handleEdit = (role: Role) => {
    setCurrentRow(role)
    setOpen('edit')
  }

  const handleDelete = (role: Role) => {
    setCurrentRow(role)
    setOpen('delete')
  }

  const handleViewPermissions = (role: Role) => {
    setCurrentRow(role)
    setOpen('view-permissions')
  }

  return (
    <div className='rounded-md border'>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Permissions</TableHead>
            <TableHead>Users</TableHead>
            <TableHead className='w-[100px]'>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className='text-center text-muted-foreground'>
                No roles found.
              </TableCell>
            </TableRow>
          ) : (
            data.map((role) => (
              <TableRow key={role.id}>
                <TableCell className='font-medium'>
                  {role.name}
                  {role.is_super_admin && (
                    <Badge variant='destructive' className='ml-2'>
                      Super Admin
                    </Badge>
                  )}
                </TableCell>
                <TableCell>
                  <Button
                    variant='link'
                    size='sm'
                    className='h-auto p-0'
                    onClick={() => handleViewPermissions(role)}
                  >
                    {role.permissions.length} permissions
                  </Button>
                </TableCell>
                <TableCell>{role.users_count} users</TableCell>
                <TableCell>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant='ghost' size='icon' className='h-8 w-8'>
                        <MoreVertical className='h-4 w-4' />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align='end'>
                      {can('view permissions') && (
                        <DropdownMenuItem onClick={() => handleViewPermissions(role)}>
                          <Shield className='mr-2 h-4 w-4' />
                          View Permissions
                        </DropdownMenuItem>
                      )}
                      {!role.is_protected && (
                        <>
                          {can('edit roles') && (
                            <DropdownMenuItem onClick={() => handleEdit(role)}>
                              <Edit className='mr-2 h-4 w-4' />
                              Edit
                            </DropdownMenuItem>
                          )}
                          {can('delete roles') && (
                            <DropdownMenuItem
                              onClick={() => handleDelete(role)}
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
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  )
}
