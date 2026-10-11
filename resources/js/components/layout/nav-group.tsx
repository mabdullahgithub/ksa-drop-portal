import { type ReactNode, useEffect, useState } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { ChevronRight, ExternalLink } from 'lucide-react'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarFooter,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  useSidebar,
} from '@/components/ui/sidebar'
import { Badge } from '../ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '../ui/dropdown-menu'
import {
  type NavCollapsible,
  type NavItem,
  type NavLink,
  type NavGroup as NavGroupProps,
} from './types'
import { usePermissions } from '@/hooks/use-permissions'

export function NavGroup({ title, icon: Icon, items, collapsible = false }: NavGroupProps) {
  const { state, isMobile } = useSidebar()
  const { url: href } = usePage()
  const { can, hasRole } = usePermissions()
  const iconOnly = state === 'collapsed' && !isMobile

  // A section that folds starts open, and reopens when the page moves inside it.
  const path = href.split('?')[0]
  const hasActive =
    collapsible &&
    items.some((item) =>
      [item, ...(item.items ?? [])].some(({ url }) => !!url && (path === url || path.startsWith(`${url}/`)))
    )
  const [open, setOpen] = useState(true)
  useEffect(() => {
    if (hasActive) setOpen(true)
  }, [hasActive])

  // Filter items based on permissions and roles; pinned ones sit in the footer
  const visibleItems = items.filter((item) => {
    if (item.pinned) return false
    if (item.permission && !can(item.permission)) return false
    if (item.role && !hasRole(item.role)) return false
    return true
  })

  // Don't render the group if no items are visible
  if (visibleItems.length === 0) return null

  const menu = (
    // Items sit a step in from the section title; the icon rail keeps them centred.
    <SidebarMenu className='ps-2 group-data-[collapsible=icon]:ps-0'>
      {visibleItems.map((item) => {
        const key = `${item.title}-${item.url}`

        if (!item.items)
          return <SidebarMenuLink key={key} item={item} href={href} />

        if (iconOnly)
          return (
            <SidebarMenuCollapsedDropdown key={key} item={item} href={href} />
          )

        return <SidebarMenuCollapsible key={key} item={item} href={href} />
      })}
    </SidebarMenu>
  )

  if (!collapsible)
    return (
      <SidebarGroup>
        <SidebarGroupLabel>
          {Icon && <Icon className='me-1.5 size-3.5!' />}
          {title}
        </SidebarGroupLabel>
        {menu}
      </SidebarGroup>
    )

  return (
    // The icon rail hides the titles, so nothing stays folded there.
    <Collapsible asChild open={open || iconOnly} onOpenChange={setOpen} className='group/section'>
      <SidebarGroup>
        <SidebarGroupLabel
          asChild
          className='w-full cursor-pointer hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
        >
          <CollapsibleTrigger>
            {Icon && <Icon className='me-1.5 size-3.5!' />}
            {title}
            <ChevronRight className='ms-auto transition-transform duration-200 group-data-[state=open]/section:rotate-90 rtl:rotate-180' />
          </CollapsibleTrigger>
        </SidebarGroupLabel>
        <CollapsibleContent className='CollapsibleContent'>{menu}</CollapsibleContent>
      </SidebarGroup>
    </Collapsible>
  )
}

const PINNED_CLASS =
  'h-9 bg-[#f26722] font-medium text-white hover:bg-[#f26722]/90 hover:text-white active:bg-[#f26722]/90 active:text-white'

/** The links stuck to the bottom of the sidebar, in the brand orange. */
export function NavPinned({ items }: { items: NavItem[] }) {
  const { url: href } = usePage()
  const { can, hasRole } = usePermissions()

  const visibleItems = items.filter((item): item is NavLink => {
    if (!item.pinned || !item.url) return false
    if (item.permission && !can(item.permission)) return false
    if (item.role && !hasRole(item.role)) return false
    return true
  })

  if (visibleItems.length === 0) return null

  return (
    // Room above for the bubble that overhangs the link; the icon rail has none.
    <SidebarFooter className='pt-4 group-data-[collapsible=icon]:pt-2'>
      <SidebarMenu>
        {visibleItems.map((item) => (
          <SidebarMenuLink key={item.url} item={item} href={href} />
        ))}
      </SidebarMenu>
    </SidebarFooter>
  )
}

function NavBadge({ children }: { children: ReactNode }) {
  return <Badge className='rounded-full px-1 py-0 text-xs'>{children}</Badge>
}

/**
 * The opens-in-a-new-tab mark on a pinned link: the bubble badge off the
 * dashboard's coloured cards (see StatCard), overhanging the top-end corner.
 * It hops now and then over a soft pulse so the eye lands on it; neither
 * moves for someone who asked for reduced motion.
 */
function NavOpenBubble() {
  return (
    <span className='pointer-events-none absolute -top-2.5 end-3 z-10 group-data-[collapsible=icon]:hidden'>
      <span
        aria-hidden
        className='absolute inset-0 animate-ping rounded-full bg-[#f26722] opacity-60 motion-reduce:hidden'
      />
      <span className='relative grid size-6 animate-[nav-open-hop_2.4s_ease-in-out_infinite] place-items-center rounded-full rounded-es-none bg-orange-900 text-white shadow-md ring-1 ring-sidebar motion-reduce:animate-none'>
        <ExternalLink className='size-3' aria-label='Opens in a new tab' />
      </span>
    </span>
  )
}

function SidebarMenuLink({ item, href }: { item: NavLink; href: string }) {
  const { setOpenMobile } = useSidebar()
  const className = item.pinned ? PINNED_CLASS : undefined

  if (item.newTab) {
    return (
      <SidebarMenuItem>
        <SidebarMenuButton asChild tooltip={item.title} className={className}>
          <a href={item.url} target='_blank' rel='noopener noreferrer'>
            {item.icon && <item.icon />}
            <span>{item.title}</span>
            {item.badge && <NavBadge>{item.badge}</NavBadge>}
            {!item.pinned && (
              <ExternalLink className='ms-auto size-3.5! opacity-60' aria-label='Opens in a new tab' />
            )}
          </a>
        </SidebarMenuButton>
        {item.pinned && <NavOpenBubble />}
      </SidebarMenuItem>
    )
  }

  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        asChild
        isActive={checkIsActive(href, item)}
        tooltip={item.title}
        className={className}
      >
        <Link href={item.url} onClick={() => setOpenMobile(false)}>
          {item.icon && <item.icon />}
          <span>{item.title}</span>
          {item.badge && <NavBadge>{item.badge}</NavBadge>}
        </Link>
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}

function SidebarMenuCollapsible({
  item,
  href,
}: {
  item: NavCollapsible
  href: string
}) {
  const { setOpenMobile } = useSidebar()
  const { can, hasRole } = usePermissions()

  // Filter sub-items based on permissions
  const visibleSubItems = item.items.filter((subItem) => {
    if (subItem.permission && !can(subItem.permission)) return false
    if (subItem.role && !hasRole(subItem.role)) return false
    return true
  })

  // Don't render if no sub-items are visible
  if (visibleSubItems.length === 0) return null

  return (
    <Collapsible
      asChild
      defaultOpen={checkIsActive(href, item, true)}
      className='group/collapsible'
    >
      <SidebarMenuItem>
        <CollapsibleTrigger asChild>
          <SidebarMenuButton tooltip={item.title}>
            {item.icon && <item.icon />}
            <span>{item.title}</span>
            {item.badge && <NavBadge>{item.badge}</NavBadge>}
            <ChevronRight className='ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 rtl:rotate-180' />
          </SidebarMenuButton>
        </CollapsibleTrigger>
        <CollapsibleContent className='CollapsibleContent'>
          <SidebarMenuSub>
            {visibleSubItems.map((subItem) => (
              <SidebarMenuSubItem key={subItem.title}>
                <SidebarMenuSubButton
                  asChild
                  isActive={checkIsActive(href, subItem)}
                >
                  <Link href={subItem.url} onClick={() => setOpenMobile(false)}>
                    {subItem.icon && <subItem.icon />}
                    <span>{subItem.title}</span>
                    {subItem.badge && <NavBadge>{subItem.badge}</NavBadge>}
                  </Link>
                </SidebarMenuSubButton>
              </SidebarMenuSubItem>
            ))}
          </SidebarMenuSub>
        </CollapsibleContent>
      </SidebarMenuItem>
    </Collapsible>
  )
}

function SidebarMenuCollapsedDropdown({
  item,
  href,
}: {
  item: NavCollapsible
  href: string
}) {
  const { can, hasRole } = usePermissions()

  // Filter sub-items based on permissions
  const visibleSubItems = item.items.filter((subItem) => {
    if (subItem.permission && !can(subItem.permission)) return false
    if (subItem.role && !hasRole(subItem.role)) return false
    return true
  })

  // Don't render if no sub-items are visible
  if (visibleSubItems.length === 0) return null

  return (
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <SidebarMenuButton
            tooltip={item.title}
            isActive={checkIsActive(href, item)}
          >
            {item.icon && <item.icon />}
            <span>{item.title}</span>
            {item.badge && <NavBadge>{item.badge}</NavBadge>}
            <ChevronRight className='ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90' />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent side='right' align='start' sideOffset={4}>
          <DropdownMenuLabel>
            {item.title} {item.badge ? `(${item.badge})` : ''}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {visibleSubItems.map((sub) => (
            <DropdownMenuItem key={`${sub.title}-${sub.url}`} asChild>
              <Link
                href={sub.url}
                className={`${checkIsActive(href, sub) ? 'bg-secondary' : ''}`}
              >
                {sub.icon && <sub.icon />}
                <span className='max-w-52 text-wrap'>{sub.title}</span>
                {sub.badge && (
                  <span className='ms-auto text-xs'>{sub.badge}</span>
                )}
              </Link>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  )
}

function checkIsActive(href: string, item: NavItem, mainNav = false) {
  return (
    href === item.url ||
    href.split('?')[0] === item.url ||
    !!item?.items?.filter((i) => i.url === href).length ||
    (mainNav &&
      href.split('/')[1] !== '' &&
      href.split('/')[1] === item?.url?.split('/')[1])
  )
}
