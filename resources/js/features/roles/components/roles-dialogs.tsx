import { RolesActionDialog } from './roles-action-dialog'
import { RolesDeleteDialog } from './roles-delete-dialog'
import { PermissionsViewDialog } from './permissions-view-dialog'

interface RolesDialogsProps {
  availablePermissions: string[]
}

export function RolesDialogs({ availablePermissions }: RolesDialogsProps) {
  return (
    <>
      <RolesActionDialog availablePermissions={availablePermissions} />
      <RolesDeleteDialog />
      <PermissionsViewDialog />
    </>
  )
}
