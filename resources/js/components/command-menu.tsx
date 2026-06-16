import React from 'react'
import { router } from '@inertiajs/react'
import { ArrowRight, ChevronRight, Laptop, Moon, Sun } from 'lucide-react'
import { useSearch } from '@/context/search-provider'
import { useTheme } from '@/context/theme-provider'
import {
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/components/ui/command'
import { CustomCommandDialog } from '@/components/custom-command-dialog'
import { useNavGroups } from '@/hooks/use-nav-groups'
import { usePermissions } from '@/hooks/use-permissions'
import { ScrollArea } from './ui/scroll-area'

export function CommandMenu() {
  const { setTheme } = useTheme()
  const { open, setOpen } = useSearch()
  const { can, hasRole } = usePermissions()
  const navGroups = useNavGroups()

  // Mirror NavGroup's per-item permission/role filtering so the command menu
  // only surfaces the tabs the current user can actually see.
  const isVisible = (item: { permission?: string | string[]; role?: string | string[] }) => {
    if (item.permission && !can(item.permission)) return false
    if (item.role && !hasRole(item.role)) return false
    return true
  }

  const visibleGroups = navGroups
    .map((group) => ({
      ...group,
      items: group.items
        .filter(isVisible)
        .map((item) =>
          item.items
            ? { ...item, items: item.items.filter(isVisible) }
            : item
        )
        .filter((item) => !item.items || item.items.length > 0),
    }))
    .filter((group) => group.items.length > 0)

  const runCommand = React.useCallback(
    (command: () => unknown) => {
      setOpen(false)
      command()
    },
    [setOpen]
  )

  return (
    <CustomCommandDialog open={open} onOpenChange={setOpen}>
      <CommandInput placeholder='Type a command or search...' />
      <CommandList>
        <ScrollArea type='hover' className='h-[400px] pe-1'>
          <CommandEmpty>No results found.</CommandEmpty>
          {visibleGroups.map((group) => (
            <CommandGroup key={group.title} heading={group.title}>
              {group.items.map((navItem, i) => {
                if (navItem.url)
                  return (
                    <CommandItem
                      key={`${navItem.url}-${i}`}
                      value={navItem.title}
                      onSelect={() => {
                        runCommand(() => router.visit(navItem.url))
                      }}
                    >
                      <div className='flex size-4 items-center justify-center'>
                        <ArrowRight className='size-2 text-muted-foreground/80' />
                      </div>
                      {navItem.title}
                    </CommandItem>
                  )

                return navItem.items?.map((subItem, i) => (
                  <CommandItem
                    key={`${navItem.title}-${subItem.url}-${i}`}
                    value={`${navItem.title} ${subItem.title}`}
                    onSelect={() => {
                      runCommand(() => router.visit(subItem.url))
                    }}
                  >
                    <div className='flex size-4 items-center justify-center'>
                      <ArrowRight className='size-2 text-muted-foreground/80' />
                    </div>
                    {navItem.title} <ChevronRight /> {subItem.title}
                  </CommandItem>
                ))
              })}
            </CommandGroup>
          ))}
          <CommandSeparator />
          <CommandGroup heading='Theme'>
            <CommandItem onSelect={() => runCommand(() => setTheme('light'))}>
              <Sun /> <span>Light</span>
            </CommandItem>
            <CommandItem onSelect={() => runCommand(() => setTheme('dark'))}>
              <Moon className='scale-90' />
              <span>Dark</span>
            </CommandItem>
            <CommandItem onSelect={() => runCommand(() => setTheme('system'))}>
              <Laptop />
              <span>System</span>
            </CommandItem>
          </CommandGroup>
        </ScrollArea>
      </CommandList>
    </CustomCommandDialog>
  )
}
