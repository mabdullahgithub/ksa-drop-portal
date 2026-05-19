import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { ScrollArea } from '@/components/ui/scroll-area'

interface User {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin?: boolean
}

interface UsersRoleDialogProps {
  open: boolean
  onOpenChange: () => void
  currentRow: User | null
  availableRoles?: string[]
}

export function UsersRoleDialog({
  open,
  onOpenChange,
  currentRow,
  availableRoles = [],
}: UsersRoleDialogProps) {
  const { data, setData, put, processing, errors, reset } = useForm({
    roles: [] as string[],
  })

  useEffect(() => {
    if (currentRow) {
      setData('roles', currentRow.roles || [])
    } else {
      reset()
    }
  }, [currentRow])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (!currentRow) return

    put(route('team-management.users.update', currentRow.id), {
      onSuccess: () => {
        onOpenChange()
        reset()
      },
    })
  }

  const toggleRole = (role: string) => {
    setData(
      'roles',
      data.roles.includes(role)
        ? data.roles.filter((r) => r !== role)
        : [...data.roles, role]
    )
  }

  if (!currentRow) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-md'>
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <DialogTitle>Assign Roles</DialogTitle>
            <DialogDescription>
              Update roles for {currentRow.name}
              {currentRow.is_super_admin && (
                <span className='block mt-2 text-destructive font-semibold'>
                  Note: Superadmin users cannot be modified.
                </span>
              )}
            </DialogDescription>
          </DialogHeader>

          <div className='space-y-4 py-4'>
            {currentRow.is_super_admin ? (
              <p className='text-sm text-muted-foreground'>
                This user has the superadmin role which cannot be changed.
              </p>
            ) : (
              <div className='space-y-2'>
                <Label>Roles</Label>
                <ScrollArea className='h-[200px] rounded-md border p-4'>
                  <div className='space-y-2'>
                    {availableRoles.map((role) => (
                      <div key={role} className='flex items-center space-x-2'>
                        <Checkbox
                          id={role}
                          checked={data.roles.includes(role)}
                          onCheckedChange={() => toggleRole(role)}
                          disabled={role === 'superadmin'}
                        />
                        <Label htmlFor={role} className='font-normal cursor-pointer capitalize'>
                          {role}
                        </Label>
                      </div>
                    ))}
                  </div>
                </ScrollArea>
                {errors.roles && (
                  <p className='text-sm text-destructive'>{errors.roles}</p>
                )}
              </div>
            )}
          </div>

          <DialogFooter>
            <Button type='button' variant='outline' onClick={onOpenChange}>
              Cancel
            </Button>
            <Button
              type='submit'
              disabled={processing || currentRow.is_super_admin}
            >
              Update Roles
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
