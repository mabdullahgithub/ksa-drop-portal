import { useMemo, useState } from 'react'
import { Search as SearchIcon } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { type CatalogModule } from '@/features/roles/data/catalog'

interface PermissionsProps {
  catalog: CatalogModule[]
  /** Which roles hold each permission, by permission name. */
  roles: Record<string, string[]>
}

/**
 * Every permission in the portal and the roles that hold it. Read-only: the
 * list is fixed by the portal itself (each page, section and button has one),
 * and who gets what is decided on the Roles page.
 */
export function Permissions({ catalog, roles }: PermissionsProps) {
  const [search, setSearch] = useState('')
  const term = search.trim().toLowerCase()

  const modules = useMemo(
    () =>
      catalog
        .map((module) => ({
          ...module,
          groups: module.groups
            .map((group) => ({
              ...group,
              permissions: group.permissions.filter(
                (p) =>
                  !term ||
                  p.label.toLowerCase().includes(term) ||
                  p.name.toLowerCase().includes(term) ||
                  p.description.toLowerCase().includes(term)
              ),
            }))
            .filter((group) => group.permissions.length > 0),
        }))
        .filter((module) => module.groups.length > 0),
    [catalog, term]
  )

  const total = catalog.reduce(
    (sum, module) => sum + module.groups.reduce((n, group) => n + group.permissions.length, 0),
    0
  )

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-3'>
          <div className='space-y-1'>
            <h2 className='text-3xl font-bold tracking-tight'>Permissions</h2>
            <p className='text-muted-foreground'>
              All {total} permissions in the portal, one for each page, section and button. Give them to
              people on the Roles page.
            </p>
          </div>
          <div className='relative w-full max-w-xs'>
            <SearchIcon className='pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder='Search permissions…'
              className='ps-8'
            />
          </div>
        </div>

        {modules.length === 0 && (
          <p className='py-12 text-center text-sm text-muted-foreground'>No permission matches “{search}”.</p>
        )}

        {/* Columns, not a grid: a grid row is as tall as its tallest card, which
            left a gap under every short one. Here each card starts right
            where the one above it ends. */}
        <div className='gap-4 xl:columns-2'>
          {modules.map((module) => (
            <Card key={module.key} className='mb-4 break-inside-avoid'>
              <CardHeader className='pb-3'>
                <CardTitle className='text-base'>{module.label}</CardTitle>
                <CardDescription>{module.description}</CardDescription>
              </CardHeader>
              <CardContent className='space-y-4'>
                {module.groups.map((group) => (
                  <div key={group.label} className='space-y-1.5'>
                    <h4 className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                      {group.label}
                    </h4>
                    <ul className='divide-y rounded-md border'>
                      {group.permissions.map((permission) => (
                        <li key={permission.name} className='flex flex-wrap items-start justify-between gap-2 px-3 py-2'>
                          <div className='min-w-0'>
                            <p className='text-sm font-medium leading-tight'>{permission.label}</p>
                            <p className='text-xs text-muted-foreground'>{permission.description}</p>
                          </div>
                          <div className='flex flex-wrap justify-end gap-1'>
                            {(roles[permission.name] ?? []).length === 0 ? (
                              <span className='text-xs text-muted-foreground'>No role</span>
                            ) : (
                              roles[permission.name].map((role) => (
                                <Badge key={role} variant='secondary' className='text-[10px] capitalize'>
                                  {role}
                                </Badge>
                              ))
                            )}
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </CardContent>
            </Card>
          ))}
        </div>
      </Main>
    </>
  )
}
