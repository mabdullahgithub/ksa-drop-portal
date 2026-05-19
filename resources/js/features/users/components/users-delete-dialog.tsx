'use client'

import { useState, useEffect } from 'react'
import { AlertTriangle } from 'lucide-react'
import { useForm, usePage } from '@inertiajs/react'
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
        onSuccess: () => {
          onOpenChange(false)
          setValue('')
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
                This action will permanently remove the user from the system. This cannot be undone.
              </p>

              <Label className='my-2'>
                Name:
                <Input
                  value={value}
                  onChange={(e) => setValue(e.target.value)}
                  placeholder='Enter user name to confirm deletion.'
                  autoFocus
                />
              </Label>

              <Alert variant='destructive'>
                <AlertTitle>Warning!</AlertTitle>
                <AlertDescription>
                  Please be careful, this operation can not be rolled back.
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
