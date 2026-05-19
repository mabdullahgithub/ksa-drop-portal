import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { Plus } from 'lucide-react'
import { usePermissions } from './permissions-provider'

export function PermissionsPrimaryButtons() {
  const { setOpen } = usePermissions()

  return (
    <div className='flex gap-2'>
      <Can permission='create permissions'>
        <Button onClick={() => setOpen('add')} size='sm' className='h-8'>
          <Plus className='mr-2' size={16} />
          Add Permission
        </Button>
      </Can>
    </div>
  )
}
