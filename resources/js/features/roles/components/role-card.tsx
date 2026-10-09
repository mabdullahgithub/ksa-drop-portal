import { Eye, Lock, Pencil, Shield, ShieldCheck, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { CardAction } from '@/components/card-action'
import { usePermissions } from '@/hooks/use-permissions'
import { cn } from '@/lib/utils'
import { type Role } from '../data/schema'
import { useRoles } from './roles-provider'

export function RoleCard({ role }: { role: Role }) {
  const { setOpen, setCurrentRow } = useRoles()
  const { can } = usePermissions()

  const openFor = (dialog: 'edit' | 'delete' | 'view-permissions') => {
    setCurrentRow(role)
    setOpen(dialog)
  }

  const canView = can('view role permissions')
  const canEdit = !role.is_protected && can('edit roles')
  const canDelete = !role.is_protected && can('delete roles')

  return (
    <div className='flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs transition-shadow hover:shadow-md'>
      {/* Header row */}
      <div className='flex items-center gap-3'>
        <span
          className={cn(
            'flex size-10 shrink-0 items-center justify-center rounded-lg',
            role.is_super_admin ? 'bg-destructive/10 text-destructive' : 'bg-primary/10 text-primary'
          )}
        >
          {role.is_super_admin ? <ShieldCheck className='size-5' /> : <Shield className='size-5' />}
        </span>

        <div className='min-w-0 flex-1'>
          <h3 className='truncate font-semibold capitalize'>{role.name}</h3>
          <p className='text-xs text-muted-foreground'>
            {role.is_protected ? 'System role' : 'Custom role'}
          </p>
        </div>

        {role.is_super_admin && (
          <Badge variant='destructive' className='shrink-0'>
            Full access
          </Badge>
        )}
      </div>

      {/* Numbers */}
      <div className='mt-auto grid grid-cols-2 divide-x border-t pt-3 text-center'>
        <Stat label='Users' value={role.users_count} />
        <Stat label='Permissions' value={role.is_super_admin ? 'All' : role.permissions.length} />
      </div>

      {/* Actions — no row when it would be empty. System roles show a lock where edit and delete would be. */}
      {(canView || canEdit || canDelete || role.is_protected) && (
        <div className='flex items-center justify-end gap-2 border-t pt-3'>
          {role.is_protected && (
            <span
              className='me-auto inline-flex items-center gap-1.5 text-xs text-muted-foreground'
              title='System roles cannot be edited or deleted'
            >
              <Lock className='size-3.5' />
              Locked
            </span>
          )}
          {canView && (
            <CardAction label='View permissions' onClick={() => openFor('view-permissions')}>
              <Eye />
            </CardAction>
          )}
          {canEdit && (
            <CardAction label='Edit role' onClick={() => openFor('edit')}>
              <Pencil />
            </CardAction>
          )}
          {canDelete && (
            <CardAction label='Delete role' onClick={() => openFor('delete')} destructive>
              <Trash2 />
            </CardAction>
          )}
        </div>
      )}
    </div>
  )
}

function Stat({ label, value }: { label: string; value: number | string }) {
  return (
    <div>
      <p className='text-lg font-semibold leading-6 tabular-nums'>{value}</p>
      <p className='text-[11px] text-muted-foreground'>{label}</p>
    </div>
  )
}
