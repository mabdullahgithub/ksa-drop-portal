import { useState } from 'react'
import { ExternalLink, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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

export function ShopifyConnectDialog({ open, onClose, lastShopDomain }: Props) {
  const [shop, setShop] = useState('')
  const [error, setError] = useState('')

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
    // Hand off to the backend, which initiates the Shopify OAuth handshake.
    window.location.href = `/portal/shopify/connect?shop=${encodeURIComponent(trimmed)}`
  }

  const handleUseLastDomain = () => {
    if (lastShopDomain) {
      setShop(lastShopDomain)
      setError('')
    }
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Connect your Shopify store</DialogTitle>
          <DialogDescription>
            Enter your Shopify store URL. You&apos;ll be redirected to Shopify to approve
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
              You can enter just &quot;mystore&quot; or the full &quot;mystore.myshopify.com&quot;.
            </p>
          </div>

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
