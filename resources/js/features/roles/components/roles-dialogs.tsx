import { RolesActionDialog } from './roles-action-dialog'
import { RolesDeleteDialog } from './roles-delete-dialog'
import { PermissionsViewDialog } from './permissions-view-dialog'

export function RolesDialogs() {
  return (
    <>
      <RolesActionDialog />
      <RolesDeleteDialog />
      <PermissionsViewDialog />
    </>
  )
}
