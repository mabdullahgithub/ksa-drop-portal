import { useForm } from '@inertiajs/react'
import { toast } from 'sonner'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { usePermissions } from './permissions-provider'

export function PermissionsDeleteDialog() {
  const { open, setOpen, currentRow } = usePermissions()
  const isOpen = open === 'delete' && currentRow

  const { delete: destroy, processing } = useForm()

  const handleDelete = () => {
    if (!currentRow) return

    destroy(route('team-management.permissions.destroy', currentRow.id), {
      onSuccess: () => {
        setOpen(null)
        toast.success('Permission deleted successfully')
      },
      onError: () => {
        toast.error('Failed to delete permission')
      },
    })
  }

  return (
    <AlertDialog open={!!isOpen} onOpenChange={() => setOpen(null)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete Permission</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete the permission "{currentRow?.name}"? This action cannot be undone.
            {currentRow?.roles.length ? (
              <span className='block mt-2 font-semibold text-destructive'>
                Warning: This permission is assigned to {currentRow.roles.length} role(s): {currentRow.roles.join(', ')}
              </span>
            ) : null}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction onClick={handleDelete} disabled={processing} className='bg-destructive hover:bg-destructive/90'>
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
