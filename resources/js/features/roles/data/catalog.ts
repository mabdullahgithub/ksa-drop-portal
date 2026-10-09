/**
 * The permission catalog as the server sends it (App\Support\PermissionCatalog):
 * every permission in the portal, grouped the way the role editor draws it.
 */
export interface CatalogPermission {
  name: string
  label: string
  description: string
  /** The permission without which this one is useless — a button's page. */
  requires: string | null
}

export interface CatalogGroup {
  label: string
  permissions: CatalogPermission[]
}

export interface CatalogModule {
  key: string
  label: string
  description: string
  groups: CatalogGroup[]
}

export function modulePermissions(module: CatalogModule): CatalogPermission[] {
  return module.groups.flatMap((group) => group.permissions)
}

/** `names` plus every page they sit on, and that page's own page. */
export function withRequired(catalog: CatalogModule[], names: string[]): string[] {
  const requires = new Map(
    catalog.flatMap(modulePermissions).map((p) => [p.name, p.requires] as const)
  )
  const all = new Set<string>()

  for (const name of names) {
    for (let current: string | null | undefined = name; current && !all.has(current); current = requires.get(current)) {
      all.add(current)
    }
  }

  return [...all]
}

/** `names` and everything that is useless without one of them. */
export function withDependents(catalog: CatalogModule[], names: string[]): string[] {
  const permissions = catalog.flatMap(modulePermissions)
  const all = new Set(names)

  // A button's page can itself need a page, so keep going until nothing is added.
  for (let grew = true; grew; ) {
    grew = false
    for (const permission of permissions) {
      if (permission.requires && all.has(permission.requires) && !all.has(permission.name)) {
        all.add(permission.name)
        grew = true
      }
    }
  }

  return [...all]
}
