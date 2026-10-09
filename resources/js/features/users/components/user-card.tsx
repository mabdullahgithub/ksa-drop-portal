import { usePage } from '@inertiajs/react'
import { Shield, Trash2, UserPen } from 'lucide-react'
import { CardAction } from '@/components/card-action'
import { Badge } from '@/components/ui/badge'
import { usePermissions } from '@/hooks/use-permissions'
import { cn } from '@/lib/utils'
import { type User } from '../data/schema'
import { OnlineDot } from './online-dot'
import { type SimpleUser } from './users-columns-simple'
import { useUsers } from './users-provider'

export function UserCard({ user }: { user: SimpleUser }) {
  const { setOpen, setCurrentRow } = useUsers()
  const { auth } = usePage().props
  const { can } = usePermissions()

  const openFor = (dialog: 'edit' | 'delete') => {
    // The provider is typed for the template's mock users; the dialogs read the real ones.
    setCurrentRow(user as unknown as User)
    setOpen(dialog)
  }

  const isSelf = user.id === auth.user.id
  // Edit opens the access dialog, which is empty for someone who may assign
  // neither roles nor clients.
  const canEdit = can('edit users') && can(['assign user roles', 'assign client access'])
  const canDelete = !user.is_super_admin && !isSelf && can('delete users')

  return (
    <div className='flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs transition-shadow hover:shadow-md'>
      {/* Header row */}
      <div className='flex items-center gap-3'>
        <span className='relative inline-flex shrink-0'>
          <span className='flex size-10 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary'>
            {initials(user.name)}
          </span>
          <OnlineDot userId={user.id} className='absolute bottom-0 end-0 size-2.5 ring-2 ring-card' />
        </span>

        <div className='min-w-0 flex-1'>
          <h3 className='truncate font-semibold'>{user.name}</h3>
          <p className='truncate text-xs text-muted-foreground'>{user.email}</p>
        </div>

        {isSelf && (
          <Badge variant='outline' className='shrink-0'>
            You
          </Badge>
        )}
      </div>

      {/* Roles */}
      <div className='flex flex-wrap gap-1'>
        {user.roles.length === 0 ? (
          <span className='text-sm text-muted-foreground'>No roles</span>
        ) : (
          user.roles.map((role) => (
            <Badge key={role} variant='secondary' className='capitalize'>
              {role}
            </Badge>
          ))
        )}
      </div>

      <div className='mt-auto grid grid-cols-2 divide-x border-t pt-3 text-center'>
        <Stat label='Clients' {...clients(user)} />
        <Stat label='Joined' value={joined(user.created_at)} />
      </div>

      {/* Actions — no row when it would be empty. */}
      {(canEdit || canDelete || user.is_super_admin) && (
        <div className='flex items-center justify-end gap-2 border-t pt-3'>
          {user.is_super_admin && (
            <span
              className='me-auto inline-flex items-center gap-1.5 text-xs text-muted-foreground'
              title='Protected - Cannot be modified or deleted'
            >
              <Shield className='size-3.5 text-amber-500' />
              Protected
            </span>
          )}
          {canEdit && (
            <CardAction label='Edit access' onClick={() => openFor('edit')}>
              <UserPen />
            </CardAction>
          )}
          {canDelete && (
            <CardAction label='Delete user' onClick={() => openFor('delete')} destructive>
              <Trash2 />
            </CardAction>
          )}
        </div>
      )}
    </div>
  )
}

function Stat({ label, value, tone }: { label: string; value: string; tone?: string }) {
  return (
    <div>
      <p className={cn('text-sm font-semibold leading-6 tabular-nums', tone)}>{value}</p>
      <p className='text-[11px] text-muted-foreground'>{label}</p>
    </div>
  )
}

/** Whose data this person handles. */
function clients(user: SimpleUser): { value: string; tone?: string } {
  // A client account only ever sees itself.
  if (user.is_client) return { value: '—' }
  if (user.client_access !== 'assigned') return { value: 'All' }

  const count = user.client_ids?.length ?? 0
  return count === 0
    ? { value: 'None', tone: 'text-amber-700 dark:text-amber-400' }
    : { value: String(count) }
}

const initials = (name: string) =>
  name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)

const joined = (value: string) =>
  new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
