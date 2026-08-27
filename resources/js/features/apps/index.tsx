import { type ChangeEvent, useState } from 'react'
import { router } from '@inertiajs/react'
import { Loader2, Settings } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePermissions } from '@/hooks/use-permissions'
import { useConnectors, type Connector } from '@/hooks/useConnectors'
import { IconShopify, IconJnt, IconImile, IconLogesTechs, IconWhatsapp } from '@/assets/brand-icons'

const logoMap: Record<string, React.ReactNode> = {
  shopify: <IconShopify />,
  jnt_express: <IconJnt />,
  imile: <IconImile />,
  logestechs: <IconLogesTechs />,
  whatsapp: <IconWhatsapp />,
}

// Fixed display order: couriers first (J&T, iMile, LogesTechs), then Shopify,
// then messaging.
const displayOrder: Record<string, number> = {
  jnt_express: 0,
  imile: 1,
  logestechs: 2,
  shopify: 3,
  whatsapp: 4,
}

// Connectors that have a dedicated settings page
const settingsRouteMap: Record<string, string> = {
  jnt_express: '/apps/jnt-express',
  imile: '/apps/imile',
  logestechs: '/apps/logestechs',
  whatsapp: '/apps/whatsapp',
}

export function Apps() {
  const [searchTerm, setSearchTerm] = useState('')
  const { can } = usePermissions()
  const { connectors, loading } = useConnectors()

  const filteredConnectors = [...connectors]
    .filter((c) => c.key !== 'coming_soon' && c.key !== 'buyease')
    .sort((a, b) => (displayOrder[a.key] ?? 99) - (displayOrder[b.key] ?? 99))
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
                showPartnerBadge={connector.key === 'shopify'}
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
  showPartnerBadge?: boolean
}

function ConnectorCard({ connector, logo, canEdit, showPartnerBadge }: ConnectorCardProps) {
  const settingsRoute = settingsRouteMap[connector.key]

  return (
    <li className='rounded-lg border p-4 hover:shadow-md'>
      <div className='mb-8 flex items-center justify-between'>
        <div className='flex size-16 items-center justify-center rounded-lg bg-muted overflow-hidden'>
          {logo}
        </div>
        <span className='inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400'>
          Active
        </span>
      </div>
      <div>
        <h2 className='mb-1 font-semibold'>{connector.name}</h2>
        <p className='line-clamp-2 text-gray-500'>{connector.description}</p>
      </div>
      {showPartnerBadge && (
        <img
          src='/images/integrations/shopify-partner.png'
          alt='Official Shopify Partner'
          className='mt-3 h-6 w-auto'
        />
      )}
      {settingsRoute && canEdit && (
        <button
          type='button'
          onClick={() => router.visit(settingsRoute)}
          className='mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted'
        >
          <Settings className='h-3.5 w-3.5' />
          Configure
        </button>
      )}
    </li>
  )
}
