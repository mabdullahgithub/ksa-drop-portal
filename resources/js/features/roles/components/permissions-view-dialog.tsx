import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { useRoles } from './roles-provider'
import { permissionCategories } from '../data/data'

export function PermissionsViewDialog() {
  const { open, setOpen, currentRow } = useRoles()
  const isOpen = open === 'view-permissions' && currentRow

  const groupedPermissions = Object.entries(permissionCategories).reduce((acc, [category, permissions]) => {
    const rolePermissions = permissions.filter((p) => currentRow?.permissions.includes(p))
    if (rolePermissions.length > 0) {
      acc[category] = rolePermissions
    }
    return acc
  }, {} as Record<string, string[]>)

  return (
    <Dialog open={!!isOpen} onOpenChange={() => setOpen(null)}>
      <DialogContent className='max-w-2xl'>
        <DialogHeader>
          <DialogTitle>Permissions for {currentRow?.name}</DialogTitle>
          <DialogDescription>
            This role has {currentRow?.permissions.length} permission(s)
          </DialogDescription>
        </DialogHeader>

        <ScrollArea className='h-[400px] pr-4'>
          {Object.keys(groupedPermissions).length === 0 ? (
            <p className='text-muted-foreground text-center py-8'>
              This role has no permissions assigned.
            </p>
          ) : (
            <div className='space-y-4'>
              {Object.entries(groupedPermissions).map(([category, permissions]) => (
                <div key={category}>
                  <h4 className='font-semibold mb-2'>{category}</h4>
                  <div className='flex flex-wrap gap-2'>
                    {permissions.map((permission) => (
                      <Badge key={permission} variant='secondary'>
                        {permission}
                      </Badge>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </ScrollArea>
      </DialogContent>
    </Dialog>
  )
}
