import { useEffect } from 'react'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useRoles } from './roles-provider'
import { PermissionPicker } from './permission-picker'
import { type CatalogModule } from '../data/catalog'
import { type Role } from '../data/schema'
import { type PageProps } from '@/types'

type RolesPageProps = PageProps<{
  roles: Role[]
  catalog: CatalogModule[]
  grantable: string[]
}>

export function RolesActionDialog() {
  const { open, setOpen, currentRow } = useRoles()
  const { roles, catalog, grantable } = usePage<RolesPageProps>().props
  const isEdit = open === 'edit' && currentRow
  const isOpen = open === 'add' || open === 'edit'

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: '',
    permissions: [] as string[],
  })

  useEffect(() => {
    if (isEdit) {
      setData({
        name: currentRow.name,
        permissions: currentRow.permissions,
      })
    } else {
      reset()
    }
  }, [isEdit, currentRow])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const options = {
      onSuccess: () => {
        setOpen(null)
        reset()
        toast.success(isEdit ? 'Role updated successfully' : 'Role created successfully')
      },
      onError: (formErrors: Record<string, string>) => {
        toast.error(formErrors.name ?? (isEdit ? 'Failed to update role' : 'Failed to create role'))
      },
    }

    if (isEdit) {
      put(route('team-management.roles.update', currentRow.id), options)
    } else {
      post(route('team-management.roles.store'), options)
    }
  }

  // Full-access roles hold everything already, so they are no use as a start.
  const templates = roles.filter((role) => !role.is_super_admin && role.permissions.length > 0)

  return (
    <Dialog open={isOpen} onOpenChange={() => setOpen(null)}>
      <DialogContent className='flex max-h-[92vh] flex-col p-0 sm:max-w-5xl'>
        <form onSubmit={handleSubmit} className='flex max-h-[92vh] flex-col'>
          <DialogHeader className='shrink-0 px-6 pt-6 pb-4'>
            <DialogTitle className='text-xl'>{isEdit ? 'Edit Role' : 'Create New Role'}</DialogTitle>
            <DialogDescription>
              Tick exactly what people with this role may see and do. Every page, section and button
              has its own permission.
            </DialogDescription>
          </DialogHeader>

          <div className='flex-1 space-y-4 overflow-y-auto px-6'>
            <div className='flex flex-wrap items-end gap-4'>
              <div className='min-w-56 flex-1 space-y-2'>
                <Label htmlFor='name'>
                  Role Name <span className='text-destructive'>*</span>
                </Label>
                <Input
                  id='name'
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder='e.g., Call agent, Account manager'
                  required
                />
              </div>

              {!isEdit && templates.length > 0 && (
                <div className='min-w-56 space-y-2'>
                  <Label>Start from an existing role</Label>
                  <Select
                    onValueChange={(id) => {
                      const template = templates.find((role) => String(role.id) === id)
                      if (template) {
                        // Only what this person may hand out themselves.
                        setData('permissions', template.permissions.filter((p) => grantable.includes(p)))
                      }
                    }}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder='Copy permissions from…' />
                    </SelectTrigger>
                    <SelectContent>
                      {templates.map((role) => (
                        <SelectItem key={role.id} value={String(role.id)} className='capitalize'>
                          {role.name} ({role.permissions.length})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
            </div>
            {errors.name && <p className='text-sm text-destructive'>{errors.name}</p>}
            {errors.permissions && <p className='text-sm text-destructive'>{errors.permissions}</p>}

            <PermissionPicker
              catalog={catalog}
              value={data.permissions}
              onChange={(permissions) => setData('permissions', permissions)}
              grantable={grantable}
            />
          </div>

          <DialogFooter className='shrink-0 border-t px-6 py-4'>
            <Button type='button' variant='outline' onClick={() => setOpen(null)} disabled={processing}>
              Cancel
            </Button>
            <Button type='submit' disabled={processing}>
              {processing && <Loader2 className='mr-2 h-4 w-4 animate-spin' />}
              {isEdit ? 'Update Role' : 'Create Role'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
