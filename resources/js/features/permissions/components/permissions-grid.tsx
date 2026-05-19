import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { Edit } from 'lucide-react'
import { type Permission } from '../data/schema'
import { usePermissions } from './permissions-provider'

interface PermissionsGridProps {
  data: Permission[]
}

// Helper function to get category label, fallback to extracting from permission name
function getCategoryLabel(permission: Permission): string {
  // Use category label if available
  if (permission.category?.label) {
    return permission.category.label
  }

  // Fallback: extract from permission name
  const actions = ['view', 'create', 'edit', 'delete', 'manage']
  let category = permission.name

  for (const action of actions) {
    if (category.startsWith(action + ' ')) {
      category = category.substring(action.length + 1)
      break
    }
  }

  // Convert to title case
  return category
    .split('-')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

// Helper function to get category sort order
function getCategorySortOrder(permission: Permission): number {
  return permission.category?.order ?? 999
}

export function PermissionsGrid({ data }: PermissionsGridProps) {
  const { setOpen, setCurrentRow } = usePermissions()
  const MAX_VISIBLE_ROLES = 2

  const handleEdit = (permission: Permission) => {
    setCurrentRow(permission)
    setOpen('edit')
  }

  // Group permissions by category
  const groupedPermissions = data.reduce((acc, permission) => {
    const categoryLabel = getCategoryLabel(permission)
    if (!acc[categoryLabel]) {
      acc[categoryLabel] = {
        permissions: [],
        order: getCategorySortOrder(permission)
      }
    }
    acc[categoryLabel].permissions.push(permission)
    return acc
  }, {} as Record<string, { permissions: Permission[], order: number }>)

  // Convert to array and sort by category order
  const sortedGroups = Object.entries(groupedPermissions)
    .map(([category, data]) => ({
      category,
      permissions: data.permissions,
      order: data.order
    }))
    .sort((a, b) => a.order - b.order)

  return (
    <TooltipProvider>
      <div className='grid gap-6 md:grid-cols-2 xl:grid-cols-3'>
        {sortedGroups.map(({ category, permissions }) => {
          if (permissions.length === 0) return null

          return (
            <Card key={category} className='flex flex-col'>
              <CardHeader>
                <CardTitle className='text-lg'>{category}</CardTitle>
                <CardDescription>
                  {permissions.length} permission{permissions.length !== 1 ? 's' : ''}
                </CardDescription>
              </CardHeader>
              <CardContent className='flex-1'>
                <div className='space-y-3'>
                  {permissions.map((permission) => {
                    const visibleRoles = permission.roles.slice(0, MAX_VISIBLE_ROLES)
                    const remainingCount = permission.roles.length - MAX_VISIBLE_ROLES

                    return (
                      <div
                        key={permission.id}
                        className='flex items-start justify-between gap-2 rounded-lg border p-3 hover:bg-muted/50 transition-colors'
                      >
                        <div className='flex-1 min-w-0'>
                          <div className='font-medium text-sm mb-1.5 truncate'>
                            {permission.name}
                          </div>
                          {permission.roles.length === 0 ? (
                            <span className='text-xs text-muted-foreground'>
                              No roles assigned
                            </span>
                          ) : (
                            <div className='flex items-center gap-1 flex-wrap'>
                              {visibleRoles.map((role) => (
                                <Badge
                                  key={role}
                                  variant='secondary'
                                  className='text-xs capitalize'
                                >
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
                                      +{remainingCount}
                                    </Badge>
                                  </TooltipTrigger>
                                  <TooltipContent side='bottom' className='max-w-xs'>
                                    <div className='flex flex-wrap gap-1'>
                                      {permission.roles.slice(MAX_VISIBLE_ROLES).map((role) => (
                                        <Badge
                                          key={role}
                                          variant='secondary'
                                          className='text-xs capitalize'
                                        >
                                          {role}
                                        </Badge>
                                      ))}
                                    </div>
                                  </TooltipContent>
                                </Tooltip>
                              )}
                            </div>
                          )}
                        </div>
                        <Button
                          variant='ghost'
                          size='icon'
                          className='h-8 w-8 shrink-0'
                          onClick={() => handleEdit(permission)}
                        >
                          <Edit className='h-4 w-4' />
                        </Button>
                      </div>
                    )
                  })}
                </div>
              </CardContent>
            </Card>
          )
        })}
      </div>
    </TooltipProvider>
  )
}
