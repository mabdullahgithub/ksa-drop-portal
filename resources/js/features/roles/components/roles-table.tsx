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
import { Lock } from 'lucide-react'
import { type Role } from '../data/schema'
import { RoleRowActions } from './role-row-actions'
import { useRoles } from './roles-provider'
import { usePermissions } from '@/hooks/use-permissions'

interface RolesTableProps {
  data: Role[]
}

export function RolesTable({ data }: RolesTableProps) {
  const { setOpen, setCurrentRow } = useRoles()
  const { can } = usePermissions()

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
                  {role.is_protected && (
                    <span title='System roles cannot be edited or deleted'>
                      <Lock className='ml-2 inline-block h-3.5 w-3.5 text-muted-foreground' />
                    </span>
                  )}
                  {role.is_super_admin && (
                    <Badge variant='destructive' className='ml-2'>
                      Full access
                    </Badge>
                  )}
                </TableCell>
                <TableCell>
                  {can('view role permissions') ? (
                    <Button
                      variant='link'
                      size='sm'
                      className='h-auto p-0'
                      onClick={() => handleViewPermissions(role)}
                    >
                      {role.is_super_admin ? 'Everything' : `${role.permissions.length} permissions`}
                    </Button>
                  ) : (
                    <span className='text-sm text-muted-foreground'>
                      {role.is_super_admin ? 'Everything' : `${role.permissions.length} permissions`}
                    </span>
                  )}
                </TableCell>
                <TableCell>{role.users_count} users</TableCell>
                <TableCell>
                  <RoleRowActions role={role} />
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  )
}
