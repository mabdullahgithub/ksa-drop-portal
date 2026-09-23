// Permission categories for organization, in display order. Keep in step with
// the permission seeders: anything missing here still shows, under "Other"
// (see groupPermissions), but should be given a proper home.
export const permissionCategories: Record<string, string[]> = {
  'Dashboard': ['view dashboard', 'create dashboard', 'edit dashboard', 'delete dashboard'],
  'Clients': ['view client', 'create client', 'edit client', 'delete client', 'impersonate client'],
  'Inventory': ['view inventory', 'create inventory', 'edit inventory', 'delete inventory'],
  'Orders': ['view orders', 'create orders', 'edit orders', 'delete orders'],
  'WhatsApp': ['view whatsapp', 'reply whatsapp'],
  // Each bin tab also needs that entity's delete permission.
  'Recycle Bin': ['view recycle bin', 'restore recycle bin', 'purge recycle bin'],
  'Apps': ['view apps', 'create apps', 'edit apps', 'delete apps'],
  'Tags': ['view tags', 'create tags', 'edit tags', 'delete tags'],
  'Users': ['view users', 'create users', 'edit users', 'delete users'],
  'Roles': ['view roles', 'create roles', 'edit roles', 'delete roles'],
  'Permissions': ['view permissions', 'create permissions', 'edit permissions', 'delete permissions'],
  'Teams': ['view teams', 'create teams', 'edit teams', 'delete teams'],
  'Notifications': ['view notifications', 'delete notifications'],
  'Settings': ['view settings', 'edit settings', 'manage-email-settings'],
}

/**
 * Groups `names` by category, keeping only the ones present, with anything
 * not listed above collected under "Other". Without that fallback a
 * permission added on the server but not here could never be granted or
 * revoked from the role editor.
 */
export function groupPermissions(names: string[]): [string, string[]][] {
  const present = new Set(names)
  const known = new Set(Object.values(permissionCategories).flat())

  const groups = Object.entries(permissionCategories)
    .map(([category, permissions]): [string, string[]] => [
      category,
      permissions.filter((p) => present.has(p)),
    ])
    .filter(([, permissions]) => permissions.length > 0)

  const other = names.filter((p) => !known.has(p)).sort()
  if (other.length > 0) groups.push(['Other', other])

  return groups
}
