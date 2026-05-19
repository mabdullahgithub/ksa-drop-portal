import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { Plus } from 'lucide-react'
import { useRoles } from './roles-provider'

export function RolesPrimaryButtons() {
  const { setOpen } = useRoles()

  return (
    <div className='flex gap-2'>
      <Can permission='create roles'>
        <Button onClick={() => setOpen('add')} size='sm' className='h-8'>
          <Plus className='mr-2' size={16} />
          Add Role
        </Button>
      </Can>
    </div>
  )
}
