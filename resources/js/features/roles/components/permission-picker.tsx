import { useMemo, useState } from 'react'
import { Search } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import {
  type CatalogModule,
  type CatalogPermission,
  modulePermissions,
  withDependents,
  withRequired,
} from '../data/catalog'

interface PermissionPickerProps {
  catalog: CatalogModule[]
  value: string[]
  /** Leave out to only show what is ticked. */
  onChange?: (permissions: string[]) => void
  /**
   * The permissions the person editing may hand out. Anything else is shown
   * but cannot be changed. Leave out to allow all.
   */
  grantable?: string[]
}

/**
 * Every permission in the portal, one module at a time, with a search across
 * all of them. Ticking a button also ticks the page it sits on; unticking a
 * page also unticks its buttons, since they would do nothing without it.
 */
export function PermissionPicker({ catalog, value, onChange, grantable }: PermissionPickerProps) {
  const [active, setActive] = useState(catalog[0]?.key ?? '')
  const [search, setSearch] = useState('')

  const selected = useMemo(() => new Set(value), [value])
  const allowed = useMemo(() => (grantable ? new Set(grantable) : null), [grantable])
  const readOnly = !onChange
  const canChange = (name: string) => !readOnly && (!allowed || allowed.has(name))

  const term = search.trim().toLowerCase()
  const matches = (p: CatalogPermission) =>
    p.label.toLowerCase().includes(term) ||
    p.name.toLowerCase().includes(term) ||
    p.description.toLowerCase().includes(term)

  // While searching, every module with a match; otherwise the one picked.
  const shown = term
    ? catalog
        .map((module) => ({
          ...module,
          groups: module.groups
            .map((group) => ({ ...group, permissions: group.permissions.filter(matches) }))
            .filter((group) => group.permissions.length > 0),
        }))
        .filter((module) => module.groups.length > 0)
    : catalog.filter((module) => module.key === active)

  const set = (names: string[], on: boolean) => {
    if (!onChange) return

    const changeable = names.filter(canChange)
    if (on) {
      const added = withRequired(catalog, changeable).filter(canChange)
      onChange([...new Set([...value, ...added])])
    } else {
      const removed = new Set(withDependents(catalog, changeable).filter(canChange))
      onChange(value.filter((name) => !removed.has(name)))
    }
  }

  const state = (names: string[]): boolean | 'indeterminate' => {
    const count = names.filter((name) => selected.has(name)).length
    return count === 0 ? false : count === names.length ? true : 'indeterminate'
  }

  const total = catalog.reduce((sum, module) => sum + modulePermissions(module).length, 0)

  return (
    <div className='space-y-3'>
      <div className='flex flex-wrap items-center gap-3'>
        <div className='relative min-w-48 flex-1'>
          <Search className='pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder='Search every permission…'
            className='ps-8'
          />
        </div>
        <span className='text-xs tabular-nums text-muted-foreground'>
          {value.length} of {total} selected
        </span>
        {!readOnly && (
          <Button
            type='button'
            variant='ghost'
            size='sm'
            className='h-7 text-xs'
            onClick={() => set(catalog.flatMap(modulePermissions).map((p) => p.name), value.length < total)}
          >
            {value.length < total ? 'Select all' : 'Clear all'}
          </Button>
        )}
      </div>

      <div className='grid gap-3 md:grid-cols-[13rem_1fr]'>
        {/* Modules */}
        <nav
          className={cn(
            'flex gap-1 overflow-x-auto rounded-lg border bg-muted/20 p-1.5 md:max-h-[26rem] md:flex-col md:overflow-y-auto',
            term && 'opacity-50'
          )}
        >
          {catalog.map((module) => {
            const names = modulePermissions(module).map((p) => p.name)
            const count = names.filter((name) => selected.has(name)).length

            return (
              <button
                key={module.key}
                type='button'
                onClick={() => {
                  setActive(module.key)
                  setSearch('')
                }}
                className={cn(
                  'flex shrink-0 items-center justify-between gap-3 rounded-md px-2.5 py-1.5 text-start text-sm transition-colors hover:bg-accent',
                  !term && module.key === active && 'bg-background font-medium shadow-sm'
                )}
              >
                <span className='truncate'>{module.label}</span>
                <span
                  className={cn(
                    'text-xs tabular-nums',
                    count === 0 ? 'text-muted-foreground/60' : count === names.length ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'
                  )}
                >
                  {count}/{names.length}
                </span>
              </button>
            )
          })}
        </nav>

        {/* Permissions */}
        <div className='space-y-4 rounded-lg border p-3 md:max-h-[26rem] md:overflow-y-auto'>
          {shown.length === 0 && (
            <p className='py-10 text-center text-sm text-muted-foreground'>No permission matches “{search}”.</p>
          )}

          {shown.map((module) => {
            const names = modulePermissions(module).map((p) => p.name)

            return (
              <section key={module.key} className='space-y-3'>
                <div className='flex items-start justify-between gap-3 border-b pb-2'>
                  <div>
                    <h4 className='text-sm font-semibold'>{module.label}</h4>
                    <p className='text-xs text-muted-foreground'>{module.description}</p>
                  </div>
                  {!readOnly && (
                    <label className='flex shrink-0 cursor-pointer items-center gap-2 text-xs text-muted-foreground'>
                      <Checkbox
                        checked={state(names)}
                        onCheckedChange={() => set(names, state(names) !== true)}
                      />
                      All
                    </label>
                  )}
                </div>

                {module.groups.map((group) => {
                  const groupNames = group.permissions.map((p) => p.name)

                  return (
                    <div key={group.label} className='space-y-1.5'>
                      <div className='flex items-center gap-2'>
                        {!readOnly && (
                          <Checkbox
                            id={`group-${module.key}-${group.label}`}
                            checked={state(groupNames)}
                            onCheckedChange={() => set(groupNames, state(groupNames) !== true)}
                          />
                        )}
                        <Label
                          htmlFor={`group-${module.key}-${group.label}`}
                          className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'
                        >
                          {group.label}
                        </Label>
                      </div>

                      <div className='grid gap-x-4 gap-y-1 sm:grid-cols-2'>
                        {group.permissions.map((permission) => {
                          const checked = selected.has(permission.name)
                          const locked = !canChange(permission.name)

                          return (
                            <label
                              key={permission.name}
                              className={cn(
                                'flex items-start gap-2 rounded-md px-1.5 py-1',
                                locked ? 'opacity-60' : 'cursor-pointer hover:bg-accent/60'
                              )}
                              title={!readOnly && locked ? 'You can only give out permissions you have yourself.' : undefined}
                            >
                              <Checkbox
                                className='mt-0.5'
                                checked={checked}
                                disabled={locked}
                                onCheckedChange={() => set([permission.name], !checked)}
                              />
                              <span className='min-w-0'>
                                <span className='block text-sm leading-tight'>{permission.label}</span>
                                <span className='block text-xs text-muted-foreground'>{permission.description}</span>
                              </span>
                            </label>
                          )
                        })}
                      </div>
                    </div>
                  )
                })}
              </section>
            )
          })}
        </div>
      </div>
    </div>
  )
}
