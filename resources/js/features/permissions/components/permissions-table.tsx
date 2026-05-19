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
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { Edit, MoreVertical } from 'lucide-react'
import { type Permission } from '../data/schema'
import { usePermissions } from './permissions-provider'
import { usePermissions as useUserPermissions } from '@/hooks/use-permissions'

interface PermissionsTableProps {
  data: Permission[]
}

export function PermissionsTable({ data }: PermissionsTableProps) {
  const { setOpen, setCurrentRow } = usePermissions()
  const { can } = useUserPermissions()

  const handleEdit = (permission: Permission) => {
    setCurrentRow(permission)
    setOpen('edit')
  }

  const MAX_VISIBLE_ROLES = 3

  return (
    <TooltipProvider>
      <div className='rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Permission Name</TableHead>
              <TableHead>Assigned To Roles</TableHead>
              <TableHead className='w-[100px]'>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={3} className='text-center text-muted-foreground'>
                  No permissions found.
                </TableCell>
              </TableRow>
            ) : (
              data.map((permission) => {
                const visibleRoles = permission.roles.slice(0, MAX_VISIBLE_ROLES)
                const remainingCount = permission.roles.length - MAX_VISIBLE_ROLES

                return (
                  <TableRow key={permission.id}>
                    <TableCell className='font-medium'>{permission.name}</TableCell>
                    <TableCell>
                      {permission.roles.length === 0 ? (
                        <span className='text-muted-foreground text-sm'>No roles</span>
                      ) : (
                        <div className='flex items-center gap-1 flex-wrap'>
                          {visibleRoles.map((role) => (
                            <Badge key={role} variant='secondary' className='text-xs'>
                              {role}
                            </Badge>
                          ))}
                          {remainingCount > 0 && (
                            <Tooltip>
                              <TooltipTrigger asChild>
                                <Badge
                                  variant='outline'
                                  className='text-xs cursor-help hover:bg-muted'
                                >
                                  +{remainingCount} more
                                </Badge>
                              </TooltipTrigger>
                              <TooltipContent side='bottom' className='max-w-xs'>
                                <div className='flex flex-wrap gap-1'>
                                  {permission.roles.slice(MAX_VISIBLE_ROLES).map((role) => (
                                    <Badge key={role} variant='secondary' className='text-xs'>
                                      {role}
                                    </Badge>
                                  ))}
                                </div>
                              </TooltipContent>
                            </Tooltip>
                          )}
                        </div>
                      )}
                    </TableCell>
                    <TableCell>
                      {can('edit permissions') && (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant='ghost' size='icon' className='h-8 w-8'>
                              <MoreVertical className='h-4 w-4' />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align='end'>
                            <DropdownMenuItem onClick={() => handleEdit(permission)}>
                              <Edit className='mr-2 h-4 w-4' />
                              Edit
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      )}
                    </TableCell>
                  </TableRow>
                )
              })
            )}
          </TableBody>
        </Table>
      </div>
    </TooltipProvider>
  )
}
