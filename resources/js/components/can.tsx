import { ReactNode } from 'react'
import { usePermissions } from '@/hooks/use-permissions'

interface CanProps {
  permission?: string | string[]
  role?: string | string[]
  requireAll?: boolean
  fallback?: ReactNode
  children: ReactNode
}

/**
 * Component to conditionally render content based on permissions or roles
 *
 * @example
 * // Show content if user has 'view users' permission
 * <Can permission="view users">
 *   <UsersList />
 * </Can>
 *
 * @example
 * // Show content if user has any of the permissions
 * <Can permission={['view users', 'edit users']}>
 *   <UsersList />
 * </Can>
 *
 * @example
 * // Show content if user has all permissions
 * <Can permission={['view users', 'edit users']} requireAll>
 *   <UsersList />
 * </Can>
 *
 * @example
 * // Show content if user has role
 * <Can role="admin">
 *   <AdminPanel />
 * </Can>
 *
 * @example
 * // Show fallback if user doesn't have permission
 * <Can permission="view users" fallback={<div>No access</div>}>
 *   <UsersList />
 * </Can>
 */
export function Can({
  permission,
  role,
  requireAll = false,
  fallback = null,
  children,
}: CanProps) {
  const { can, canAll, hasRole, hasAllRoles } = usePermissions()

  let hasAccess = true

  // Check permissions
  if (permission) {
    if (Array.isArray(permission) && requireAll) {
      hasAccess = canAll(permission)
    } else {
      hasAccess = can(permission)
    }
  }

  // Check roles
  if (role && hasAccess) {
    if (Array.isArray(role) && requireAll) {
      hasAccess = hasAllRoles(role)
    } else {
      hasAccess = hasRole(role)
    }
  }

  return hasAccess ? <>{children}</> : <>{fallback}</>
}
