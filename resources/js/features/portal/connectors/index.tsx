import { useEffect, useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import {
  Loader2,
  Sparkles,
  ChevronDown,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
} from 'lucide-react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useEnabledConnectors, type Connector } from '@/hooks/useEnabledConnectors'
import { useShopifyConnection } from '@/hooks/useShopifyConnection'
import { IconShopify, IconJnt } from '@/assets/brand-icons'
import { type PageProps } from '@/types'
import { ShopifyConnectDialog } from './shopify-connect-dialog'

const logoMap: Record<string, React.ReactNode> = {
  shopify: <IconShopify />,
  jnt_express: <IconJnt />,
}

export function PortalConnectors() {
  const { connectors, comingSoon, loading, refresh } = useEnabledConnectors()
  const { props } = usePage<PageProps>()

  // Surface the OAuth callback result (redirected back with a flash message).
  useEffect(() => {
    if (props.flash?.success) toast.success(props.flash.success)
    if (props.flash?.error) toast.error(props.flash.error)
  }, [props.flash?.success, props.flash?.error])

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
            {connectors.map((connector) =>
              connector.key === 'shopify' ? (
                <ShopifyConnectorCard
                  key={connector.id}
                  connector={connector}
                  logo={logoMap[connector.key]}
                  onChanged={refresh}
                />
              ) : (
                <ConnectorCard
                  key={connector.id}
                  connector={connector}
                  logo={logoMap[connector.key]}
                />
              )
            )}
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
        <div className='bg-muted flex size-16 items-center justify-center rounded-lg overflow-hidden'>
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

function ShopifyConnectorCard({
  connector,
  logo,
  onChanged,
}: ConnectorCardProps & { onChanged: () => void }) {
  const [showConnect, setShowConnect] = useState(false)
  const {
    disconnect,
    disconnecting,
    updateSyncMode,
    updatingSyncMode,
    retryWebhooks,
    retrying,
  } = useShopifyConnection()

  const connected = !!connector.client_connected
  const needsReconnect = !!connector.needs_reconnect
  const isManual = connector.sync_mode === 'manual_approval'
  const webhooksOk = connector.webhooks_registered !== false

  const handleRetryWebhooks = async () => {
    const { ok, message } = await retryWebhooks()
    if (ok) {
      toast.success(message || 'Live order sync is now active.')
      onChanged()
    } else {
      toast.error(message || 'Could not enable live order sync.')
    }
  }

  const handleDisconnect = async () => {
    const ok = await disconnect()
    if (ok) {
      toast.success('Shopify store disconnected.')
      onChanged()
    } else {
      toast.error('Could not disconnect. Please try again.')
    }
  }

  const handleSyncModeChange = async (autoSync: boolean) => {
    const mode = autoSync ? 'auto_sync' : 'manual_approval'
    const ok = await updateSyncMode(mode)
    if (ok) {
      toast.success(
        autoSync
          ? 'Orders will now sync automatically.'
          : 'Orders will now wait for your review.'
      )
      onChanged()
    } else {
      toast.error('Could not update sync mode.')
    }
  }

  const lastSynced = connector.last_synced_at
    ? new Date(connector.last_synced_at).toLocaleString()
    : null
  const lastConnected = connector.connected_at
    ? new Date(connector.connected_at).toLocaleString()
    : null

  // ── Connected: show the store information card ──
  if (connected) {
    return (
      <li className='rounded-lg border p-4 hover:shadow-md'>
        <div className='mb-4 flex items-center justify-between'>
          <div className='bg-muted flex size-16 items-center justify-center rounded-lg overflow-hidden'>
            {logo}
          </div>
          <DropdownMenu>
            <DropdownMenuTrigger
              disabled={disconnecting}
              className='inline-flex cursor-pointer items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 outline-none hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 dark:hover:bg-emerald-900'
            >
              {disconnecting ? (
                <Loader2 className='h-3 w-3 animate-spin' />
              ) : (
                <>
                  <CheckCircle2 className='h-3 w-3' />
                  Connected
                  <ChevronDown className='h-3 w-3 opacity-50' />
                </>
              )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align='end' className='min-w-32'>
              <DropdownMenuItem
                className='text-xs text-red-600 focus:text-red-600 dark:text-red-400'
                onSelect={handleDisconnect}
              >
                Disconnect
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <div>
          <h2 className='font-semibold'>{connector.name}</h2>
          {connector.shop_domain && (
            <p className='text-muted-foreground text-sm'>{connector.shop_domain}</p>
          )}
          {lastSynced && (
            <p className='text-muted-foreground mt-0.5 text-xs'>Last synced: {lastSynced}</p>
          )}
        </div>

        <div className='bg-border my-3 h-px' />

        {/* Sync mode toggle */}
        <div className='space-y-1.5'>
          <div className='flex items-center justify-between'>
            <span className='text-sm font-medium'>
              {isManual ? 'Manual approval' : 'Auto-sync'}
            </span>
            <Switch
              checked={!isManual}
              disabled={updatingSyncMode}
              onCheckedChange={handleSyncModeChange}
              aria-label='Toggle auto-sync'
            />
          </div>
          <p className='text-muted-foreground text-xs'>
            {isManual
              ? 'New orders wait in your Shopify queue until you submit them.'
              : 'New orders appear in your orders list automatically.'}
          </p>
        </div>

        {/* Webhook setup warning — live order sync not active yet */}
        {!webhooksOk && (
          <div className='mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300'>
            <div className='flex items-start gap-2'>
              <AlertTriangle className='mt-0.5 h-3.5 w-3.5 shrink-0' />
              <div className='space-y-1.5'>
                <p className='font-medium'>Live order sync is not active yet.</p>
                <p>
                  New orders won&apos;t appear automatically until webhook setup
                  completes. This usually requires protected customer data approval
                  on the Shopify app.
                </p>
                <Button
                  size='sm'
                  variant='outline'
                  className='h-6 text-[11px]'
                  disabled={retrying}
                  onClick={handleRetryWebhooks}
                >
                  {retrying ? (
                    <Loader2 className='mr-1 h-3 w-3 animate-spin' />
                  ) : null}
                  Retry setup
                </Button>
              </div>
            </div>
          </div>
        )}

        {/* Pending review banner (manual mode only) */}
        {isManual && (connector.pending_count ?? 0) > 0 && (
          <button
            type='button'
            onClick={() => router.visit('/portal/orders?tab=shopify-queue')}
            className='mt-3 flex w-full items-center justify-between rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-left text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900'
          >
            <span>
              {connector.pending_count} order
              {connector.pending_count === 1 ? '' : 's'} pending review
            </span>
            <span className='inline-flex items-center gap-1'>
              Review <ArrowRight className='h-3 w-3' />
            </span>
          </button>
        )}
      </li>
    )
  }

  // ── Not connected (or token error → reconnect) ──
  return (
    <li className='rounded-lg border p-4 hover:shadow-md'>
      <div className='mb-8 flex items-center justify-between'>
        <div className='bg-muted flex size-16 items-center justify-center rounded-lg overflow-hidden'>
          {logo}
        </div>
        {needsReconnect ? (
          <span className='inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-600 dark:bg-amber-900 dark:text-amber-400'>
            <AlertTriangle className='h-3 w-3' />
            Reconnect needed
          </span>
        ) : (
          <Button
            size='sm'
            variant='outline'
            className='h-7 text-xs'
            onClick={() => setShowConnect(true)}
          >
            Connect Store
          </Button>
        )}
      </div>

      <div>
        <h2 className='mb-1 font-semibold'>{connector.name}</h2>
        <p className='line-clamp-2 text-gray-500'>{connector.description}</p>
        {lastConnected && (
          <p className='text-muted-foreground mt-2 text-xs'>
            Last connected: {lastConnected}
          </p>
        )}
        {needsReconnect && (
          <Button
            size='sm'
            variant='outline'
            className='mt-3 h-7 text-xs'
            onClick={() => setShowConnect(true)}
          >
            Reconnect
          </Button>
        )}
      </div>

      <ShopifyConnectDialog open={showConnect} onClose={() => setShowConnect(false)} lastShopDomain={connector.shop_domain} />
    </li>
  )
}

function ComingSoonCard() {
  return (
    <li className='rounded-lg border border-dashed border-amber-300 bg-gradient-to-br from-amber-50 to-orange-50 p-4 opacity-75 dark:border-amber-600 dark:from-amber-950 dark:to-orange-950'>
      <div className='mb-8 flex items-center justify-between'>
        <div className='flex size-16 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900'>
          <Sparkles className='h-8 w-8 text-amber-600 dark:text-amber-400' />
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
