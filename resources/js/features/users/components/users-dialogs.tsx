import { UsersActionDialog } from './users-action-dialog'
import { UsersDeleteDialog } from './users-delete-dialog'
import { UsersRoleDialog } from './users-role-dialog'
import { useUsers } from './users-provider'

interface UsersDialogsProps {
  availableRoles?: string[]
  availablePermissions?: string[]
}

export function UsersDialogs({ availableRoles, availablePermissions }: UsersDialogsProps = {}) {
  const { open, setOpen, currentRow, setCurrentRow } = useUsers()
  const isRealData = currentRow && typeof currentRow.id === 'number'

  return (
    <>
      {!isRealData && (
        <UsersActionDialog
          key='user-add'
          open={open === 'add'}
          onOpenChange={() => setOpen('add')}
          availableRoles={availableRoles}
          availablePermissions={availablePermissions}
        />
      )}

      {currentRow && (
        <>
          {isRealData ? (
            <UsersRoleDialog
              key={`user-role-${currentRow.id}`}
              open={open === 'edit'}
              onOpenChange={() => {
                setOpen('edit')
                setTimeout(() => {
                  setCurrentRow(null)
                }, 500)
              }}
              currentRow={currentRow}
              availableRoles={availableRoles}
            />
          ) : (
            <UsersActionDialog
              key={`user-edit-${currentRow.id}`}
              open={open === 'edit'}
              onOpenChange={() => {
                setOpen('edit')
                setTimeout(() => {
                  setCurrentRow(null)
                }, 500)
              }}
              currentRow={currentRow}
              availableRoles={availableRoles}
              availablePermissions={availablePermissions}
            />
          )}

          <UsersDeleteDialog
            key={`user-delete-${currentRow.id}`}
            open={open === 'delete'}
            onOpenChange={() => {
              setOpen('delete')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
          />
        </>
      )}
    </>
  )
}
