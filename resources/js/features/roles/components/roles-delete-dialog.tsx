import { useEffect, useState } from 'react'
import { AlertTriangle } from 'lucide-react'
import { useForm } from '@inertiajs/react'
import { toast } from 'sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { useRoles } from './roles-provider'

export function RolesDeleteDialog() {
  const { open, setOpen, currentRow } = useRoles()
  const isOpen = open === 'delete' && !!currentRow

  const [value, setValue] = useState('')
  const { delete: destroy, processing } = useForm()

  const name = currentRow?.name ?? ''
  const users = currentRow?.users_count ?? 0
  // A role people still hold stays: the server refuses it too.
  const inUse = users > 0

  useEffect(() => {
    if (!isOpen) {
      setValue('')
    }
  }, [isOpen])

  const handleDelete = () => {
    if (!currentRow || inUse || value.trim() !== name) return

    destroy(route('team-management.roles.destroy', currentRow.id), {
      preserveScroll: true,
      onSuccess: () => {
        setOpen(null)
        toast.success('Role deleted successfully')
      },
      onError: (errors) => {
        toast.error(Object.values(errors)[0] ?? 'Could not delete this role.')
      },
    })
  }

  return (
    <ConfirmDialog
      open={isOpen}
      onOpenChange={() => setOpen(null)}
      form='roles-delete-form'
      disabled={inUse || value.trim() !== name || processing}
      title={
        <span className='text-destructive'>
          <AlertTriangle className='me-1 inline-block stroke-destructive' size={18} /> Delete Role
        </span>
      }
      desc={
        <form
          id='roles-delete-form'
          onSubmit={(e) => {
            e.preventDefault()
            handleDelete()
          }}
          className='space-y-4'
        >
          {inUse ? (
            <Alert variant='destructive'>
              <AlertTitle>Cannot delete this role</AlertTitle>
              <AlertDescription>
                <span>
                  <span className='font-bold'>{name}</span> is assigned to {users}{' '}
                  {users === 1 ? 'user' : 'users'}. Move them to another role first, then delete
                  it.
                </span>
              </AlertDescription>
            </Alert>
          ) : (
            <>
              <p className='mb-2'>
                Are you sure you want to delete the role{' '}
                <span className='font-bold'>{name}</span>?
                <br />
                This action cannot be undone.
              </p>

              <Label className='my-2'>
                Type the role name exactly to enable Delete:
                <Input
                  value={value}
                  onChange={(e) => setValue(e.target.value)}
                  placeholder={`Type "${name}" to confirm`}
                  autoFocus
                />
              </Label>
            </>
          )}
        </form>
      }
      confirmText={processing ? 'Deleting...' : 'Delete'}
      destructive
    />
  )
}
