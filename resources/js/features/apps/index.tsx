import { type ChangeEvent, useState } from 'react'
import { SlidersHorizontal, ArrowUpAZ, ArrowDownAZ, ChevronDown, Loader2 } from 'lucide-react'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Separator } from '@/components/ui/separator'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePermissions } from '@/hooks/use-permissions'
import { useConnectors, type Connector } from '@/hooks/useConnectors'
import { IconShopify, IconJnt } from '@/assets/brand-icons'
import { cn } from '@/lib/utils'

const logoMap: Record<string, React.ReactNode> = {
  shopify: <IconShopify />,
  jnt_express: <IconJnt />,
}

export function Apps() {
  const [sort, setSort] = useState<'asc' | 'desc'>('asc')
  const [searchTerm, setSearchTerm] = useState('')
  const { can } = usePermissions()
  const { connectors, loading, toggling, toggle } = useConnectors()

  const filteredConnectors = [...connectors]
    .sort((a, b) =>
      sort === 'asc' ? a.name.localeCompare(b.name) : b.name.localeCompare(a.name)
    )
    .filter((c) => c.name.toLowerCase().includes(searchTerm.toLowerCase()))

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main fixed>
        <div>
          <h1 className='text-2xl font-bold tracking-tight'>App Integrations</h1>
          <p className='text-muted-foreground'>
            Here&apos;s a list of your apps for the integration!
          </p>
        </div>
        <div className='my-4 flex items-end justify-between sm:my-0 sm:items-center'>
          <div className='flex flex-col gap-4 sm:my-4 sm:flex-row'>
            <Input
              placeholder='Filter apps...'
              className='h-9 w-40 lg:w-62.5'
              value={searchTerm}
              onChange={(e: ChangeEvent<HTMLInputElement>) => setSearchTerm(e.target.value)}
            />
          </div>

          <Select value={sort} onValueChange={(v: 'asc' | 'desc') => setSort(v)}>
            <SelectTrigger className='w-16'>
              <SelectValue>
                <SlidersHorizontal size={18} />
              </SelectValue>
            </SelectTrigger>
            <SelectContent align='end'>
              <SelectItem value='asc'>
                <div className='flex items-center gap-4'>
                  <ArrowUpAZ size={16} />
                  <span>Ascending</span>
                </div>
              </SelectItem>
              <SelectItem value='desc'>
                <div className='flex items-center gap-4'>
                  <ArrowDownAZ size={16} />
                  <span>Descending</span>
                </div>
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Separator className='shadow-sm' />

        {loading ? (
          <div className='flex items-center justify-center pt-16'>
            <Loader2 className='text-muted-foreground h-6 w-6 animate-spin' />
          </div>
        ) : (
          <ul className='faded-bottom no-scrollbar grid gap-4 overflow-auto overscroll-contain pt-4 pb-16 md:grid-cols-2 lg:grid-cols-3'>
            {filteredConnectors.map((connector) => (
              <ConnectorCard
                key={connector.id}
                connector={connector}
                logo={logoMap[connector.key]}
                canEdit={can('edit apps')}
                isToggling={toggling === connector.id}
                onToggle={() => toggle(connector)}
              />
            ))}
          </ul>
        )}
      </Main>
    </>
  )
}

interface ConnectorCardProps {
  connector: Connector
  logo: React.ReactNode
  canEdit: boolean
  isToggling: boolean
  onToggle: () => void
}

function ConnectorCard({ connector, logo, canEdit, isToggling, onToggle }: ConnectorCardProps) {
  return (
    <li className='rounded-lg border p-4 hover:shadow-md'>
      <div className='mb-8 flex items-center justify-between'>
        <div className='flex size-10 items-center justify-center rounded-lg bg-muted p-2'>
          {logo}
        </div>
        {canEdit && (
          <DropdownMenu>
            <DropdownMenuTrigger
              disabled={isToggling}
              className={cn(
                'inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium transition-colors outline-none',
                connector.enabled
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 dark:hover:bg-emerald-900'
                  : 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-800 dark:bg-red-950 dark:text-red-400 dark:hover:bg-red-900'
              )}
            >
              {isToggling ? (
                <Loader2 className='h-3 w-3 animate-spin' />
              ) : (
                <>
                  {connector.enabled ? 'Enabled' : 'Disabled'}
                  <ChevronDown className='h-3 w-3 opacity-50' />
                </>
              )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align='end' className='min-w-28'>
              <DropdownMenuItem
                disabled={connector.enabled}
                onSelect={onToggle}
                className='text-xs text-emerald-700 focus:text-emerald-700 dark:text-emerald-400'
              >
                Enable
              </DropdownMenuItem>
              <DropdownMenuItem
                disabled={!connector.enabled}
                onSelect={onToggle}
                className='text-xs text-red-600 focus:text-red-600 dark:text-red-400'
              >
                Disable
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </div>
      <div>
        <h2 className='mb-1 font-semibold'>{connector.name}</h2>
        <p className='line-clamp-2 text-gray-500'>{connector.description}</p>
      </div>
    </li>
  )
}
