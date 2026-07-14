import { useState } from 'react'
import {
  ArrowLeft,
  BookOpen,
  ChevronDown,
  ExternalLink,
  LogIn,
  RotateCcw,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

interface Props {
  open: boolean
  onClose: () => void
  lastShopDomain?: string | null
}

/** Strip protocol, path and trailing slash so we can inspect the bare host. */
function toBareHost(input: string): string {
  return input
    .replace(/^https?:\/\//i, '')
    .replace(/\/.*$/, '')
    .toLowerCase()
}

/** "mystore" and "mystore.myshopify.com" both resolve to the permanent host. */
function toShopDomain(input: string): string {
  const host = toBareHost(input)

  return host.endsWith('.myshopify.com') ? host : `${host}.myshopify.com`
}

export function ShopifyConnectDialog({ open, onClose, lastShopDomain }: Props) {
  const [shop, setShop] = useState('')
  const [error, setError] = useState('')
  const [guideOpen, setGuideOpen] = useState(false)
  const [signInStep, setSignInStep] = useState(false)

  const handleConnect = () => {
    const trimmed = shop.trim()
    if (!trimmed) {
      setError('Please enter your store URL.')
      return
    }
    if (trimmed.includes(' ')) {
      setError('Store URL cannot contain spaces.')
      return
    }
    // Custom domains (e.g. mystore.com) cannot be used for the Shopify OAuth
    // handshake — only the permanent *.myshopify.com host works.
    const host = toBareHost(trimmed)
    if (host.includes('.') && !host.endsWith('.myshopify.com')) {
      setError(
        'That looks like a custom domain. Please enter your permanent .myshopify.com store URL instead — see the guide below to find it.'
      )
      setGuideOpen(true)
      return
    }
    setSignInStep(true)
  }

  // Shopify's grant screen lives on admin.shopify.com, which answers 403
  // "Unauthorized Access" — not a login prompt — when the merchant has no admin
  // session. Its own login hop drops the OAuth redirect, so we send merchants to
  // the store login themselves before handing off to the handshake.
  const handleSignIn = () => {
    window.open(
      `https://${toShopDomain(shop)}/admin/auth/login`,
      '_blank',
      'noopener,noreferrer'
    )
  }

  // Hand off to the backend, which initiates the Shopify OAuth handshake.
  const handleContinue = () => {
    window.location.href = `/portal/shopify/connect?shop=${encodeURIComponent(shop.trim())}`
  }

  const handleUseLastDomain = () => {
    if (lastShopDomain) {
      setShop(lastShopDomain)
      setError('')
    }
  }

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      setSignInStep(false)
      onClose()
    }
  }

  if (signInStep) {
    return (
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent className='sm:max-w-md'>
          <DialogHeader>
            <DialogTitle>Sign in to Shopify, then continue</DialogTitle>
            <DialogDescription>
              Shopify only shows the install screen to a merchant who is already signed
              in to <b>{toShopDomain(shop)}</b>. If you aren&apos;t, it answers with an
              &quot;Unauthorized Access&quot; error page instead of asking you to log in.
            </DialogDescription>
          </DialogHeader>

          <div className='space-y-4 pt-2'>
            <ol className='space-y-3 text-sm'>
              <li className='flex gap-3'>
                <span className='flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium'>
                  1
                </span>
                <div className='space-y-2'>
                  <p>
                    Open your Shopify admin in a new tab and sign in. Skip this if
                    you&apos;re already signed in.
                  </p>
                  <Button variant='outline' size='sm' onClick={handleSignIn}>
                    <LogIn className='mr-2 h-3.5 w-3.5' />
                    Sign in to Shopify
                  </Button>
                </div>
              </li>
              <li className='flex gap-3'>
                <span className='flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium'>
                  2
                </span>
                <p>
                  Come back here and continue. Shopify will show the permissions screen —
                  click <b>Install</b> to approve, and you&apos;ll land back in the portal.
                </p>
              </li>
            </ol>

            <div className='flex justify-end gap-2'>
              <Button variant='outline' onClick={() => setSignInStep(false)}>
                <ArrowLeft className='mr-2 h-4 w-4' />
                Back
              </Button>
              <Button onClick={handleContinue}>
                <ExternalLink className='mr-2 h-4 w-4' />
                Continue to Shopify
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Connect your Shopify store</DialogTitle>
          <DialogDescription>
            Enter your <b>.myshopify.com</b> store URL — your custom domain (e.g.
            mystore.com) will not work. You&apos;ll be redirected to Shopify to approve
            the connection. Once connected, your orders sync into the portal automatically.
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-4 pt-2'>
          <div className='space-y-2'>
            <Label htmlFor='shop-domain'>Store URL</Label>
            <Input
              id='shop-domain'
              placeholder='mystore.myshopify.com'
              value={shop}
              onChange={(e) => {
                setShop(e.target.value)
                setError('')
              }}
              onKeyDown={(e) => e.key === 'Enter' && handleConnect()}
              autoFocus
            />
            {error && <p className='text-sm text-red-500'>{error}</p>}
            <p className='text-muted-foreground text-xs'>
              Enter just &quot;mystore&quot; or the full &quot;mystore.myshopify.com&quot;.
              Custom domains (e.g. mystore.com) won&apos;t work.
            </p>
          </div>

          <Collapsible open={guideOpen} onOpenChange={setGuideOpen}>
            <CollapsibleTrigger asChild>
              <button
                type='button'
                className='flex w-full items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm font-medium hover:bg-muted/70 transition-colors'
              >
                <BookOpen className='h-4 w-4 text-muted-foreground' />
                How to find your .myshopify.com store URL
                <ChevronDown
                  className={`ml-auto h-4 w-4 text-muted-foreground transition-transform ${guideOpen ? 'rotate-180' : ''}`}
                />
              </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <div className='mt-2 space-y-3 rounded-lg border bg-muted/20 p-4 text-sm'>
                <div>
                  <p className='font-medium mb-1'>Option 1 — from your admin URL (fastest)</p>
                  <p className='text-muted-foreground'>
                    Log in to your Shopify admin. The address bar shows{' '}
                    <code className='rounded bg-background px-1 py-0.5 font-mono text-xs border'>
                      admin.shopify.com/store/<b>your-handle</b>
                    </code>
                    . Your store URL is{' '}
                    <code className='rounded bg-background px-1 py-0.5 font-mono text-xs border'>
                      <b>your-handle</b>.myshopify.com
                    </code>
                    .
                  </p>
                </div>
                <div>
                  <p className='font-medium mb-1'>Option 2 — from Shopify settings</p>
                  <p className='text-muted-foreground'>
                    In Shopify admin, go to <b>Settings → Domains</b>. Copy the domain
                    ending in <b>.myshopify.com</b> — it&apos;s listed as your default or
                    store domain.
                  </p>
                </div>
                <div>
                  <p className='font-medium mb-1'>Then connect</p>
                  <ol className='list-decimal space-y-1 pl-4 text-muted-foreground'>
                    <li>Paste the .myshopify.com URL above and click <b>Connect Store</b>.</li>
                    <li>Sign in to your Shopify admin if you aren&apos;t already, then continue.</li>
                    <li>Review the permissions and click <b>Install</b> to approve.</li>
                    <li>You&apos;ll return here and your orders start syncing automatically.</li>
                  </ol>
                </div>
                <p className='rounded-md border border-amber-200/60 bg-amber-100/40 px-3 py-2 text-xs text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-400'>
                  Note: your custom storefront domain (e.g. mystore.com) cannot be used to
                  connect — Shopify only allows the permanent .myshopify.com address.
                </p>
              </div>
            </CollapsibleContent>
          </Collapsible>

          {lastShopDomain && (
            <div className='rounded-lg bg-green-100/40 p-3 border border-green-200/50 dark:bg-green-950/30 dark:border-green-900/50'>
              <p className='text-xs font-medium text-green-700 dark:text-green-400 mb-2'>Last connected store:</p>
              <div className='flex items-center gap-2'>
                <code className='flex-1 text-sm font-mono bg-background px-2.5 py-1.5 rounded border border-green-200/50 dark:border-green-900/50'>
                  {lastShopDomain}
                </code>
                <Button
                  variant='outline'
                  size='sm'
                  onClick={handleUseLastDomain}
                  title='Use last connected domain'
                  className='shrink-0'
                >
                  <RotateCcw className='h-3.5 w-3.5' />
                </Button>
              </div>
            </div>
          )}

          <div className='flex justify-end gap-2'>
            <Button variant='outline' onClick={onClose}>
              Cancel
            </Button>
            <Button onClick={handleConnect} disabled={!shop.trim()}>
              <ExternalLink className='mr-2 h-4 w-4' />
              Connect Store
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
