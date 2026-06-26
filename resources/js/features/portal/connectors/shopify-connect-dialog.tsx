import { useState } from 'react'
import { ExternalLink } from 'lucide-react'
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
}

export function ShopifyConnectDialog({ open, onClose }: Props) {
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
