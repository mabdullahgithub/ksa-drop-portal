import { usePage } from '@inertiajs/react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useRoles } from './roles-provider'
import { PermissionPicker } from './permission-picker'
import { type PageProps } from '@/types'
import { type CatalogModule } from '../data/catalog'

export function PermissionsViewDialog() {
  const { open, setOpen, currentRow } = useRoles()
  const { catalog } = usePage<PageProps<{ catalog: CatalogModule[] }>>().props
  const isOpen = open === 'view-permissions' && currentRow

  return (
    <Dialog open={!!isOpen} onOpenChange={() => setOpen(null)}>
      <DialogContent className='max-h-[92vh] overflow-y-auto sm:max-w-5xl'>
        <DialogHeader>
          <DialogTitle className='capitalize'>Permissions for {currentRow?.name}</DialogTitle>
          <DialogDescription>
            {currentRow?.is_super_admin
              ? 'This role has full access: everything, always, including anything added later.'
              : `This role has ${currentRow?.permissions.length ?? 0} permission(s).`}
          </DialogDescription>
        </DialogHeader>

        <PermissionPicker catalog={catalog} value={currentRow?.permissions ?? []} />
      </DialogContent>
    </Dialog>
  )
}
