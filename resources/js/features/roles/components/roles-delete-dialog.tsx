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
import { useRoles } from './roles-provider'

export function RolesDeleteDialog() {
  const { open, setOpen, currentRow } = useRoles()
  const isOpen = open === 'delete' && currentRow

  const { delete: destroy, processing } = useForm()

  const handleDelete = () => {
    if (!currentRow) return

    destroy(route('team-management.roles.destroy', currentRow.id), {
      onSuccess: () => {
        setOpen(null)
        toast.success('Role deleted successfully')
      },
      onError: () => {
        toast.error('Failed to delete role. It may be in use.')
      },
    })
  }

  return (
    <AlertDialog open={!!isOpen} onOpenChange={() => setOpen(null)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete Role</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete the role "{currentRow?.name}"? This action cannot be undone.
            {currentRow?.users_count ? (
              <span className='block mt-2 font-semibold text-destructive'>
                Warning: This role is assigned to {currentRow.users_count} user(s).
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
