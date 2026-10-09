import { useForm, usePage } from '@inertiajs/react'
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
import { PermissionPicker } from '@/features/roles/components/permission-picker'
import { type CatalogModule } from '@/features/roles/data/catalog'
import { type PageProps } from '@/types'

interface CreateRoleDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onRoleCreated: (roleName: string) => void
}

export function CreateRoleDialog({ open, onOpenChange, onRoleCreated }: CreateRoleDialogProps) {
  const { catalog, grantable } = usePage<PageProps<{ catalog: CatalogModule[]; grantable: string[] }>>().props

  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    permissions: [] as string[],
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    // The dialog sits inside the user form; keep this submit from reaching it.
    e.stopPropagation()

    post(route('team-management.roles.store'), {
      onSuccess: () => {
        toast.success('Role created successfully')
        onRoleCreated(data.name)
        reset()
      },
      onError: () => {
        toast.error('Failed to create role')
      },
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='flex max-h-[92vh] flex-col p-0 sm:max-w-5xl'>
        <form onSubmit={handleSubmit} className='flex max-h-[92vh] flex-col'>
          <DialogHeader className='shrink-0 px-6 pt-6 pb-4'>
            <DialogTitle className='text-xl'>Create New Role</DialogTitle>
            <DialogDescription>
              Tick exactly what people with this role may see and do.
            </DialogDescription>
          </DialogHeader>

          <div className='flex-1 space-y-4 overflow-y-auto px-6'>
            <div className='space-y-2'>
              <Label htmlFor='role-name'>
                Role Name <span className='text-destructive'>*</span>
              </Label>
              <Input
                id='role-name'
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder='e.g., Call agent, Account manager'
                className='max-w-md'
                required
              />
              {errors.name && <p className='text-sm text-destructive'>{errors.name}</p>}
            </div>

            <PermissionPicker
              catalog={catalog ?? []}
              value={data.permissions}
              onChange={(permissions) => setData('permissions', permissions)}
              grantable={grantable}
            />
          </div>

          <DialogFooter className='shrink-0 border-t px-6 py-4'>
            <Button type='button' variant='outline' onClick={() => onOpenChange(false)} disabled={processing}>
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
