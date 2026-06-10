import { useTags } from './tags-provider'
import { TagsActionDialog } from './tags-action-dialog'
import { TagsDeleteDialog } from './tags-delete-dialog'

export function TagsDialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useTags()

  const closeAndClear = (dialog: 'edit' | 'delete') => {
    setOpen(dialog)
    setTimeout(() => setCurrentRow(null), 500)
  }

  return (
    <>
      <TagsActionDialog
        key='tag-add'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
      />

      {currentRow && (
        <>
          <TagsActionDialog
            key={`tag-edit-${currentRow.id}`}
            open={open === 'edit'}
            onOpenChange={() => closeAndClear('edit')}
            currentRow={currentRow}
          />
          <TagsDeleteDialog
            key={`tag-delete-${currentRow.id}`}
            open={open === 'delete'}
            onOpenChange={() => closeAndClear('delete')}
            currentRow={currentRow}
          />
        </>
      )}
    </>
  )
}
