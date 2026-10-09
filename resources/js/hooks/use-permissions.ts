import { usePage } from '@inertiajs/react'
import { PageProps } from '@/types'

/**
 * Hook to check user permissions
 */
export function usePermissions() {
  const { auth } = usePage<PageProps>().props
  const permissions = auth?.permissions || []
  const roles = auth?.roles || []
  // superadmin and developer: nothing is ever checked for them.
  const fullAccess = !!auth?.full_access

  /**
   * Check if user has a specific permission
   */
  const can = (permission: string | string[]): boolean => {
    if (fullAccess) return true
    if (Array.isArray(permission)) {
      return permission.some((p) => permissions.includes(p))
    }
    return permissions.includes(permission)
  }

  /**
   * Check if user has all specified permissions
   */
  const canAll = (permissionList: string[]): boolean => {
    return fullAccess || permissionList.every((p) => permissions.includes(p))
  }

  /**
   * Check if user has a specific role
   */
  const hasRole = (role: string | string[]): boolean => {
    if (Array.isArray(role)) {
      return role.some((r) => roles.includes(r))
    }
    return roles.includes(role)
  }

  /**
   * Check if user has all specified roles
   */
  const hasAllRoles = (roleList: string[]): boolean => {
    return roleList.every((r) => roles.includes(r))
  }

  /**
   * For the few things the client portal shares with the team (the bell, the
   * profile menu): a client account, or staff looking at a client's portal,
   * always has them — the portal has no staff permissions.
   */
  const isPortal = roles.includes('client') || !!auth?.impersonating
  const canOrPortal = (permission: string | string[]): boolean => isPortal || can(permission)

  return {
    permissions,
    roles,
    fullAccess,
    can,
    canOrPortal,
    canAll,
    hasRole,
    hasAllRoles,
  }
}
