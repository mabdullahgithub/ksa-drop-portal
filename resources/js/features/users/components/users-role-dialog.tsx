import { useEffect } from 'react'
import { useForm, usePage } from '@inertiajs/react'
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
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { ScrollArea } from '@/components/ui/scroll-area'
import { usePermissions } from '@/hooks/use-permissions'
import { type PageProps } from '@/types'
import { type AssignableClient, type ClientAccessMode, CLIENT_ACCESS_MODES, ClientAccessField } from './client-access-field'

interface User {
  id: number
  name: string
  email: string
  roles: string[]
  is_super_admin?: boolean
  is_client?: boolean
  client_access?: ClientAccessMode
  client_ids?: number[]
}

interface UsersRoleDialogProps {
  open: boolean
  onOpenChange: () => void
  currentRow: User | null
  availableRoles?: string[]
}

/**
 * What a team member can reach: their roles (what they may do) and their
 * clients (whose data they may do it to). Each half is its own permission.
 */
export function UsersRoleDialog({
  open,
  onOpenChange,
  currentRow,
  availableRoles = [],
}: UsersRoleDialogProps) {
  const { can } = usePermissions()
  const { clients = [], grantableClientModes = CLIENT_ACCESS_MODES } = usePage<PageProps<{ clients?: AssignableClient[]; grantableClientModes?: ClientAccessMode[] }>>().props

  const canRoles = can('assign user roles')
  // Client accounts are not team members: they only ever see themselves.
  const canClients = can('assign client access') && !currentRow?.is_client

  const { data, setData, transform, put, processing, errors, reset } = useForm({
    roles: [] as string[],
    client_access: 'all' as ClientAccessMode,
    client_ids: [] as number[],
  })

  useEffect(() => {
    if (currentRow) {
      setData({
        roles: currentRow.roles || [],
        client_access: currentRow.client_access ?? 'all',
        client_ids: currentRow.client_ids ?? [],
      })
    } else {
      reset()
    }
  }, [currentRow])

  // Send only the halves this person may change.
  transform((form) => ({
    ...(canRoles ? { roles: form.roles } : {}),
    ...(canClients ? { client_access: form.client_access, client_ids: form.client_ids } : {}),
  }))

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (!currentRow) return

    put(route('team-management.users.update', currentRow.id), {
      onSuccess: () => {
        toast.success(`${currentRow.name} updated`)
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

  // Roles the person holds that this editor may not hand out: shown, but fixed.
  const fixedRoles = data.roles.filter((role) => !availableRoles.includes(role))

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[92vh] max-w-md overflow-y-auto'>
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <DialogTitle>Access for {currentRow.name}</DialogTitle>
            <DialogDescription>
              Roles decide what they can do. Clients decide whose data they can do it to.
              {currentRow.is_super_admin && (
                <span className='block mt-2 text-destructive font-semibold'>
                  This user has full access, which cannot be limited.
                </span>
              )}
            </DialogDescription>
          </DialogHeader>

          <div className='space-y-5 py-4'>
            {currentRow.is_super_admin ? (
              <p className='text-sm text-muted-foreground'>
                Super admin and developer accounts can do everything and see every client.
              </p>
            ) : (
              <>
                {canRoles && (
                  <div className='space-y-2'>
                    <Label>Roles</Label>
                    <ScrollArea className='h-[180px] rounded-md border p-4'>
                      <div className='space-y-2'>
                        {[...fixedRoles, ...availableRoles].map((role) => (
                          <div key={role} className='flex items-center space-x-2'>
                            <Checkbox
                              id={`role-${role}`}
                              checked={data.roles.includes(role)}
                              onCheckedChange={() => toggleRole(role)}
                              disabled={fixedRoles.includes(role)}
                            />
                            <Label htmlFor={`role-${role}`} className='font-normal cursor-pointer capitalize'>
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

                {canClients && (
                  <ClientAccessField
                    mode={data.client_access}
                    clientIds={data.client_ids}
                    onChange={(mode, ids) => setData((form) => ({ ...form, client_access: mode, client_ids: ids }))}
                    clients={clients}
                    modes={grantableClientModes}
                    error={errors.client_access ?? errors.client_ids}
                  />
                )}
              </>
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
              Save access
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
