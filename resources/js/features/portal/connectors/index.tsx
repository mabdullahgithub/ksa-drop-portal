import { Loader2, Sparkles } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { useEnabledConnectors, type Connector } from '@/hooks/useEnabledConnectors'
import { IconShopify, IconJnt } from '@/assets/brand-icons'

const logoMap: Record<string, React.ReactNode> = {
  shopify: <IconShopify />,
  jnt_express: <IconJnt />,
}

export function PortalConnectors() {
  const { connectors, comingSoon, loading } = useEnabledConnectors()

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
          <h1 className='text-2xl font-bold tracking-tight'>Available Integrations</h1>
          <p className='text-muted-foreground'>
            Connect your store with our available integrations
          </p>
        </div>

        {loading ? (
          <div className='flex items-center justify-center pt-16'>
            <Loader2 className='text-muted-foreground h-6 w-6 animate-spin' />
          </div>
        ) : (
          <ul className='faded-bottom no-scrollbar grid gap-4 overflow-auto overscroll-contain pt-4 pb-16 md:grid-cols-2 lg:grid-cols-3'>
            {connectors.map((connector) => (
              <ConnectorCard
                key={connector.id}
                connector={connector}
                logo={logoMap[connector.key]}
              />
            ))}
            {comingSoon && <ComingSoonCard />}
          </ul>
        )}
      </Main>
    </>
  )
}

interface ConnectorCardProps {
  connector: Connector
  logo: React.ReactNode
}

function ConnectorCard({ connector, logo }: ConnectorCardProps) {
  return (
    <li className='rounded-lg border p-4 hover:shadow-md'>
      <div className='mb-8 flex items-center justify-between'>
        <div className='flex size-10 items-center justify-center rounded-lg bg-muted p-2'>
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
    </li>
  )
}

function ComingSoonCard() {
  return (
    <li className='rounded-lg border border-dashed border-amber-300 bg-gradient-to-br from-amber-50 to-orange-50 p-4 opacity-75 dark:border-amber-600 dark:from-amber-950 dark:to-orange-950'>
      <div className='mb-8 flex items-center justify-between'>
        <div className='flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900'>
          <Sparkles className='h-5 w-5 text-amber-600 dark:text-amber-400' />
        </div>
        <span className='inline-flex items-center rounded-md border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:border-amber-600 dark:bg-amber-900 dark:text-amber-400'>
          Coming Soon
        </span>
      </div>
      <div>
        <h2 className='mb-1 font-semibold text-gray-800 dark:text-gray-200'>
          Something Special
        </h2>
        <p className='line-clamp-2 text-sm text-gray-600 dark:text-gray-400'>
          Exciting new integrations coming for our valued clients. Stay tuned!
        </p>
      </div>
    </li>
  )
}
