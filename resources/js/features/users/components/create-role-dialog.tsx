import { useState } from 'react'
import { useForm } from '@inertiajs/react'
import { toast } from 'sonner'
import { Loader2 } from 'lucide-react'
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
import { Checkbox } from '@/components/ui/checkbox'
import { permissionCategories } from '@/features/roles/data/data'

interface CreateRoleDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onRoleCreated: (roleName: string) => void
  availablePermissions: string[]
}

export function CreateRoleDialog({
  open,
  onOpenChange,
  onRoleCreated,
  availablePermissions,
}: CreateRoleDialogProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    permissions: [] as string[],
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    post(route('team-management.roles.store'), {
      onSuccess: () => {
        toast.success('Role created successfully')
        const createdRoleName = data.name.toLowerCase()
        onRoleCreated(createdRoleName)
        reset()
      },
      onError: () => {
        toast.error('Failed to create role')
      },
    })
  }

  const togglePermission = (permission: string) => {
    setData(
      'permissions',
      data.permissions.includes(permission)
        ? data.permissions.filter((p) => p !== permission)
        : [...data.permissions, permission]
    )
  }

  const toggleCategory = (category: string[]) => {
    const allSelected = category.every((p) => data.permissions.includes(p))
    if (allSelected) {
      setData(
        'permissions',
        data.permissions.filter((p) => !category.includes(p))
      )
    } else {
      setData('permissions', [...new Set([...data.permissions, ...category])])
    }
  }

  const selectAll = () => {
    setData('permissions', availablePermissions)
  }

  const deselectAll = () => {
    setData('permissions', [])
  }

  const selectedCount = data.permissions.length
  const totalCount = availablePermissions.length

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-4xl max-h-[90vh] flex flex-col p-0'>
        <form onSubmit={handleSubmit} className='flex flex-col h-full max-h-[90vh]'>
          <DialogHeader className='px-6 pt-6 pb-4 flex-shrink-0'>
            <DialogTitle className='text-xl'>Create New Role</DialogTitle>
            <DialogDescription>
              Create a new role and assign permissions to control access levels.
            </DialogDescription>
          </DialogHeader>

          <div className='space-y-4 px-6 flex-1 overflow-y-auto'>
            {/* Role Name Input */}
            <div className='space-y-2'>
              <Label htmlFor='role-name'>
                Role Name <span className='text-destructive'>*</span>
              </Label>
              <Input
                id='role-name'
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder='e.g., Manager, Editor, Viewer'
                className='max-w-md'
                required
              />
              {errors.name && (
                <p className='text-sm text-destructive'>{errors.name}</p>
              )}
            </div>

            {/* Permissions Section */}
            <div className='space-y-3'>
              <div className='flex items-center justify-between sticky top-0 bg-background z-10 pb-2'>
                <Label className='text-sm font-semibold'>Permissions (Optional)</Label>
                <div className='flex items-center gap-3'>
                  <Button
                    type='button'
                    variant='ghost'
                    size='sm'
                    onClick={selectedCount === totalCount ? deselectAll : selectAll}
                    className='h-7 text-xs'
                  >
                    {selectedCount === totalCount ? 'Deselect All' : 'Select All'}
                  </Button>
                  <span className='text-xs text-muted-foreground'>
                    {selectedCount}/{totalCount} selected
                  </span>
                </div>
              </div>

              {errors.permissions && (
                <p className='text-sm text-destructive'>
                  {errors.permissions}
                </p>
              )}

              {/* Permissions Grid */}
              <div className='rounded-lg border bg-muted/20 p-4'>
                <div className='grid grid-cols-1 md:grid-cols-2 gap-4'>
                    {Object.entries(permissionCategories).map(
                      ([category, permissions]) => {
                        const categoryPerms = permissions.filter((p) =>
                          availablePermissions.includes(p)
                        )
                        if (categoryPerms.length === 0) return null

                        const allSelected = categoryPerms.every((p) =>
                          data.permissions.includes(p)
                        )
                        const someSelected = categoryPerms.some((p) =>
                          data.permissions.includes(p)
                        )

                        return (
                          <div
                            key={category}
                            className='rounded-lg border bg-background p-3 space-y-2'
                          >
                            {/* Category Header */}
                            <div className='flex items-center justify-between pb-2 border-b'>
                              <div className='flex items-center space-x-2'>
                                <Checkbox
                                  id={`cat-${category}`}
                                  checked={
                                    allSelected
                                      ? true
                                      : someSelected && !allSelected
                                        ? 'indeterminate'
                                        : false
                                  }
                                  onCheckedChange={() =>
                                    toggleCategory(categoryPerms)
                                  }
                                />
                                <Label
                                  htmlFor={`cat-${category}`}
                                  className='font-semibold text-xs cursor-pointer uppercase'
                                >
                                  {category}
                                </Label>
                              </div>
                            </div>

                            {/* Permission Checkboxes */}
                            <div className='space-y-2'>
                              {categoryPerms.map((permission) => (
                                <div
                                  key={permission}
                                  className='flex items-center space-x-2'
                                >
                                  <Checkbox
                                    id={`perm-${permission}`}
                                    checked={data.permissions.includes(permission)}
                                    onCheckedChange={() =>
                                      togglePermission(permission)
                                    }
                                  />
                                  <Label
                                    htmlFor={`perm-${permission}`}
                                    className='font-normal text-xs cursor-pointer'
                                  >
                                    {permission}
                                  </Label>
                                </div>
                              ))}
                            </div>
                          </div>
                        )
                      }
                    )}
                  </div>
              </div>
            </div>
          </div>

          <DialogFooter className='px-6 py-4 border-t flex-shrink-0'>
            <Button
              type='button'
              variant='outline'
              onClick={() => onOpenChange(false)}
              disabled={processing}
            >
              Cancel
            </Button>
            <Button type='submit' disabled={processing}>
              {processing && <Loader2 className='mr-2 h-4 w-4 animate-spin' />}
              Create Role
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
