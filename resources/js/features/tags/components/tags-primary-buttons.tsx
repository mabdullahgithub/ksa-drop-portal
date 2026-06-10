import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { useTags } from './tags-provider'

export function TagsPrimaryButtons() {
  const { setOpen } = useTags()
  return (
    <Can permission='create tags'>
      <Button onClick={() => setOpen('add')}>
        <Plus size={16} className='me-1' />
        Add Tag
      </Button>
    </Can>
  )
}
