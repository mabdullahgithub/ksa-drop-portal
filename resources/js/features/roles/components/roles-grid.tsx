import { EmptyState } from '@/components/empty-state'
import { type Role } from '../data/schema'
import { RoleCard } from './role-card'

export function RolesGrid({ data }: { data: Role[] }) {
  if (data.length === 0) {
    return (
      <EmptyState
        bot='clover'
        state='sleeping'
        title='No roles found.'
        className='flex-1 rounded-xl border border-dashed py-16'
      />
    )
  }

  return (
    <div className='grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'>
      {data.map((role) => (
        <RoleCard key={role.id} role={role} />
      ))}
    </div>
  )
}
