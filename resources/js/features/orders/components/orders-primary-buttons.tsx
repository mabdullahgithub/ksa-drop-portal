import { Download, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { useOrders } from './orders-provider'

export function OrdersPrimaryButtons() {
  const { setOpen } = useOrders()
  return (
    <div className='flex gap-2'>
      <Can permission='create orders'>
        <Button
          variant='outline'
          className='space-x-1'
          onClick={() => setOpen('import')}
        >
          <span>Import</span> <Download size={18} />
        </Button>
        <Button className='space-x-1' onClick={() => setOpen('create')}>
          <span>Create</span> <Plus size={18} />
        </Button>
      </Can>
    </div>
  )
}
