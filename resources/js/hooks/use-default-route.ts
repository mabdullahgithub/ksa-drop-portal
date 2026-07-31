import { usePermissions } from '@/hooks/use-permissions'

/**
 * Ordered list of the primary pages, each with the permission required to view
 * it. Mirrors the sidebar navigation order and the server-side
 * `User::defaultLandingRoute()` so the client and server agree on where a user
 * should land.
 */
const LANDING_CANDIDATES: { permission: string; url: string }[] = [
  { permission: 'view dashboard', url: '/dashboard' },
  { permission: 'view client', url: '/client' },
  { permission: 'view inventory', url: '/inventory' },
  { permission: 'view orders', url: '/orders' },
  { permission: 'view apps', url: '/apps' },
  { permission: 'view tags', url: '/tags' },
  { permission: 'view users', url: '/team-management/users' },
]

/**
 * Resolve the URL of the first page the current user is allowed to view.
 *
 * Clients are always sent to their portal. For everyone else we walk the
 * landing candidates in priority order and return the first one they have
 * permission for. Returns `null` when the user can't view any of them — callers
 * should treat that as "nowhere to fall back to" (e.g. render a 403).
 */
export function useDefaultRoute(): string | null {
  const { can, hasRole } = usePermissions()

  if (hasRole('client')) {
    return '/portal'
  }

  const match = LANDING_CANDIDATES.find((c) => can(c.permission))
  return match?.url ?? null
}
