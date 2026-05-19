import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import { toast } from 'sonner'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { usePermissions } from './permissions-provider'

export function PermissionsActionDialog() {
  const { open, setOpen, currentRow } = usePermissions()
  const isEdit = open === 'edit' && currentRow
  const isOpen = open === 'edit'

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: '',
  })

  useEffect(() => {
    if (isEdit) {
      setData('name', currentRow.name)
    } else {
      reset()
    }
  }, [isEdit, currentRow])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (isEdit && currentRow) {
      put(route('team-management.permissions.update', currentRow.id), {
        onSuccess: () => {
          setOpen(null)
          reset()
          toast.success('Permission updated successfully')
        },
        onError: () => {
          toast.error('Failed to update permission')
        },
      })
    }
  }

  return (
    <Dialog open={isOpen} onOpenChange={() => setOpen(null)}>
      <DialogContent>
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <DialogTitle>Edit Permission</DialogTitle>
            <DialogDescription>
              Update the permission name.
            </DialogDescription>
          </DialogHeader>

          <div className='space-y-4 py-4'>
            <div className='space-y-2'>
              <Label htmlFor='name'>Permission Name</Label>
              <Input
                id='name'
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder='e.g., view reports'
              />
              {errors.name && (
                <p className='text-sm text-destructive'>{errors.name}</p>
              )}
            </div>
          </div>

          <DialogFooter>
            <Button type='button' variant='outline' onClick={() => setOpen(null)}>
              Cancel
            </Button>
            <Button type='submit' disabled={processing}>
              Update Permission
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
