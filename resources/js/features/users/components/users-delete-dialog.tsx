'use client'

import { useState, useEffect } from 'react'
import { AlertTriangle } from 'lucide-react'
import { useForm, usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type User as SchemaUser } from '../data/schema'

interface LaravelUser {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin?: boolean
}

type UserDeleteDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: SchemaUser | LaravelUser
}

export function UsersDeleteDialog({
  open,
  onOpenChange,
  currentRow,
}: UserDeleteDialogProps) {
  const [value, setValue] = useState('')
  const { delete: destroy, processing } = useForm()
  const { auth } = usePage<{ auth: { user: { id: number } } }>().props

  // Check if this is real Laravel data or mock data
  const isRealData = currentRow && typeof currentRow.id === 'number'
  const isSuperAdmin = isRealData
    ? (currentRow as LaravelUser).is_super_admin
    : (currentRow as SchemaUser).role === 'superadmin'
  const isSelf = isRealData && auth && currentRow.id === auth.user.id
  const displayName = isRealData
    ? (currentRow as LaravelUser).name
    : (currentRow as SchemaUser).username

  useEffect(() => {
    if (!open) {
      setValue('')
    }
  }, [open])

  const handleDelete = () => {
    if (value.trim() !== displayName) return

    if (isRealData) {
      destroy(route('team-management.users.destroy', currentRow.id), {
        preserveScroll: true,
        onSuccess: (page) => {
          onOpenChange(false)
          setValue('')
          const flash = (page.props as { flash?: { success?: string } }).flash
          toast.success(flash?.success ?? `${displayName} was moved to the recycle bin.`)
        },
        // The server refuses with a validation-style error (e.g. superadmin);
        // without this the dialog would just sit there.
        onError: (errors) => {
          toast.error(Object.values(errors)[0] ?? 'Could not delete this user.')
        },
      })
    } else {
      // Mock data - just close dialog
      onOpenChange(false)
      setValue('')
    }
  }

  // Prevent deletion of superadmin or self
  const canDelete = !isSuperAdmin && !isSelf
  const disabledReason = isSuperAdmin
    ? 'Superadmin users cannot be deleted.'
    : isSelf
    ? 'You cannot delete your own account.'
    : null

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      form='users-delete-form'
      disabled={!canDelete || value.trim() !== displayName || processing}
      title={
        <span className='text-destructive'>
          <AlertTriangle
            className='me-1 inline-block stroke-destructive'
            size={18}
          />{' '}
          Delete User
        </span>
      }
      desc={
        <form
          id='users-delete-form'
          onSubmit={(e) => {
            e.preventDefault()
            handleDelete()
          }}
          className='space-y-4'
        >
          {!canDelete ? (
            <Alert variant='destructive'>
              <AlertTitle>Cannot Delete User</AlertTitle>
              <AlertDescription>{disabledReason}</AlertDescription>
            </Alert>
          ) : (
            <>
              <p className='mb-2'>
                Are you sure you want to delete{' '}
                <span className='font-bold'>{displayName}</span>?
                <br />
                They will lose access right away and move to the recycle bin, where they can be restored.
              </p>

              <Label className='my-2'>
                Type the name exactly to enable Delete:
                <Input
                  value={value}
                  onChange={(e) => setValue(e.target.value)}
                  placeholder={`Type "${displayName}" to confirm`}
                  autoFocus
                />
              </Label>

              <Alert variant='destructive'>
                <AlertTitle>Heads up</AlertTitle>
                <AlertDescription>
                  They are signed out immediately. Restore them from the recycle bin if this was a mistake.
                </AlertDescription>
              </Alert>
            </>
          )}
        </form>
      }
      confirmText={processing ? 'Deleting...' : 'Delete'}
      destructive
    />
  )
}
