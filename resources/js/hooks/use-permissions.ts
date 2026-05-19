import { usePage } from '@inertiajs/react'
import { PageProps } from '@/types'

/**
 * Hook to check user permissions
 */
export function usePermissions() {
  const { auth } = usePage<PageProps>().props
  const permissions = auth?.permissions || []
  const roles = auth?.roles || []

  /**
   * Check if user has a specific permission
   */
  const can = (permission: string | string[]): boolean => {
    if (Array.isArray(permission)) {
      return permission.some((p) => permissions.includes(p))
    }
    return permissions.includes(permission)
  }

  /**
   * Check if user has all specified permissions
   */
  const canAll = (permissionList: string[]): boolean => {
    return permissionList.every((p) => permissions.includes(p))
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

  return {
    permissions,
    roles,
    can,
    canAll,
    hasRole,
    hasAllRoles,
  }
}
