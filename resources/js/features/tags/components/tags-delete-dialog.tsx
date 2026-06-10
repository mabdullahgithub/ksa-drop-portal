import { useState } from 'react'
import { router } from '@inertiajs/react'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type Tag } from '../data/schema'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: Tag
}

export function TagsDeleteDialog({ open, onOpenChange, currentRow }: Props) {
  const [isLoading, setIsLoading] = useState(false)

  const handleDelete = () => {
    setIsLoading(true)
    router.delete(route('tags.destroy', currentRow.id), {
      onSuccess: () => onOpenChange(false),
      onFinish:  () => setIsLoading(false),
    })
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      title='Delete Tag'
      desc={
        <span>
          Are you sure you want to delete the tag{' '}
          <strong className='font-semibold'>{currentRow.name}</strong>? This action cannot be undone.
        </span>
      }
      confirmText='Delete'
      destructive
      isLoading={isLoading}
      handleConfirm={handleDelete}
    />
  )
}
