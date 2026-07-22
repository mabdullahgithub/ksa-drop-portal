import { useEffect, useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import {
  Loader2,
  ChevronDown,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
  RefreshCw,
  ClipboardCheck,
  Zap,
  Store,
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
import { BuyEaseCard } from '@/components/buyease-card'
import { useEnabledConnectors, type Connector } from '@/hooks/useEnabledConnectors'
import { useShopifyConnection } from '@/hooks/useShopifyConnection'
import { IconShopify, IconJnt } from '@/assets/brand-icons'
import { type PageProps } from '@/types'
import { ShopifyConnectDialog } from './shopify-connect-dialog'

const logoMap: Record<string, React.ReactNode> = {
  shopify: <IconShopify />,
  jnt_express: <IconJnt />,
}

// Fixed display order: J&T first, then Shopify, then BuyEase.
// (J&T is filtered out server-side for clients but kept here for safety.)
const displayOrder: Record<string, number> = {
  jnt_express: 0,
  shopify: 1,
  buyease: 2,
}

export function PortalConnectors() {
  const { connectors, loading, refresh } = useEnabledConnectors()

  const orderedConnectors = [...connectors].sort(
    (a, b) => (displayOrder[a.key] ?? 99) - (displayOrder[b.key] ?? 99)
  )
  const { props } = usePage<PageProps>()

  // Deep link from the embedded Shopify admin app: ?shop=<domain> opens the
  // connect dialog with the store prefilled so nothing is typed manually.
  const [prefillShop] = useState(
    () => new URLSearchParams(window.location.search).get('shop') || ''
  )

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
            {orderedConnectors.map((connector) =>
              connector.key === 'shopify' ? (
                <ShopifyConnectorCard
                  key={connector.id}
                  connector={connector}
                  logo={logoMap[connector.key]}
                  onChanged={refresh}
                  prefillShop={prefillShop}
                />
              ) : connector.key === 'buyease' ? (
                <BuyEaseCard
                  key={connector.id}
                  name={connector.name}
                  description={connector.description}
                />
              ) : (
                <ConnectorCard
                  key={connector.id}
                  connector={connector}
                  logo={logoMap[connector.key]}
                />
              )
            )}
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
  prefillShop,
}: ConnectorCardProps & { onChanged: () => void; prefillShop?: string }) {
  const [showConnect, setShowConnect] = useState(false)

  // Arriving via the embedded-app deep link (?shop=...): open the dialog
  // immediately with the store prefilled, unless it's already connected.
  useEffect(() => {
    if (prefillShop && !connector.client_connected) {
      setShowConnect(true)
    }
  }, [prefillShop, connector.client_connected])
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
    <li className='group relative overflow-hidden rounded-lg border border-emerald-200/80 bg-gradient-to-br from-emerald-50/70 via-background to-background p-4 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-100/50 dark:border-emerald-900/70 dark:from-emerald-950/30 dark:hover:border-emerald-700 dark:hover:shadow-emerald-950/30'>
      {/* Soft brand glow */}
      <div
        aria-hidden
        className='pointer-events-none absolute -top-14 -right-14 size-36 rounded-full bg-emerald-400/15 blur-2xl transition-transform duration-500 group-hover:scale-125 dark:bg-emerald-500/10'
      />

      <div className='relative mb-4 flex items-start justify-between'>
        <div className='flex size-16 items-center justify-center overflow-hidden rounded-xl shadow-md ring-1 ring-emerald-900/10 dark:ring-white/10'>
          {logo}
        </div>
        {needsReconnect ? (
          <span className='inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-600 dark:bg-amber-900 dark:text-amber-400'>
            <AlertTriangle className='h-3 w-3' />
            Reconnect needed
          </span>
        ) : (
          <span className='text-muted-foreground inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium'>
            Not connected
          </span>
        )}
      </div>

      <div className='relative'>
        <h2 className='mb-1 font-semibold'>{connector.name}</h2>
        <p className='line-clamp-2 text-sm text-gray-500 dark:text-gray-400'>
          {connector.description}
        </p>
        {lastConnected && (
          <p className='text-muted-foreground mt-2 text-xs'>
            Last connected: {lastConnected}
          </p>
        )}
      </div>

      <ul className='relative mt-3 space-y-1.5 text-xs'>
        <li className='flex items-center gap-2'>
          <RefreshCw className='h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400' />
          Orders sync to KSA Drop automatically
        </li>
        <li className='flex items-center gap-2'>
          <ClipboardCheck className='h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400' />
          Optional manual approval — you stay in control
        </li>
        <li className='flex items-center gap-2'>
          <Zap className='h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400' />
          Real-time updates via secure webhooks
        </li>
      </ul>

      <div className='bg-border relative my-3 h-px' />

      {/* Reconnect reopens the app inside Shopify Admin — the only place
          OAuth is allowed to start (App Store review requirement 2.3.1) —
          rather than a portal-hosted form. */}
      {needsReconnect && connector.admin_app_url ? (
        <Button
          asChild
          size='sm'
          className='relative w-full bg-[#008060] text-white hover:bg-[#006e52] dark:bg-[#008060] dark:hover:bg-[#009973]'
        >
          <a href={connector.admin_app_url} target='_blank' rel='noopener noreferrer'>
            <Store className='h-3.5 w-3.5' />
            Reconnect Store
            <ArrowRight className='h-3.5 w-3.5' />
          </a>
        </Button>
      ) : (
        <Button
          size='sm'
          className='relative w-full bg-[#008060] text-white hover:bg-[#006e52] dark:bg-[#008060] dark:hover:bg-[#009973]'
          onClick={() => setShowConnect(true)}
        >
          <Store className='h-3.5 w-3.5' />
          Connect Store
          <ArrowRight className='h-3.5 w-3.5' />
        </Button>
      )}

      <ShopifyConnectDialog
        open={showConnect}
        onClose={() => setShowConnect(false)}
        shopDomain={prefillShop || undefined}
        appStoreUrl={connector.app_store_url}
        onLinked={onChanged}
      />
    </li>
  )
}
