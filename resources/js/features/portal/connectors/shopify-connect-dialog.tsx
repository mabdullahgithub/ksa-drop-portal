import { ExternalLink, Loader2, Store } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useShopifyConnection } from '@/hooks/useShopifyConnection'

interface Props {
  open: boolean
  onClose: () => void
  /** Shop domain from the embedded-app deep link (?shop=...) — never typed. */
  shopDomain?: string
  /** Signed proof-of-shop token from the same deep link (?claim_token=...). */
  claimToken?: string
  /** Link to the Shopify App Store listing, shown when there's no shopDomain. */
  appStoreUrl?: string | null
  onLinked: () => void
}

/**
 * Links an already-installed Shopify store to this KSA Drop client, or (if
 * the merchant hasn't installed yet) points them to the Shopify App Store.
 *
 * There is deliberately no store-URL input here. Shopify App Store review
 * requirement 2.3.1 forbids installation/authorization starting from manual
 * domain entry on a non-Shopify surface — the shop domain only ever comes
 * from the deep link Shopify's own embedded app passes us, and linking is a
 * plain account-attach call, never a new OAuth handshake. The claim token
 * traveling alongside it proves that deep link actually came from that
 * shop's own Shopify Admin session, not just a URL someone typed.
 */
export function ShopifyConnectDialog({
  open,
  onClose,
  shopDomain,
  claimToken,
  appStoreUrl,
  onLinked,
}: Props) {
  const { claimStore, claiming } = useShopifyConnection()

  const handleLink = async () => {
    if (!shopDomain || !claimToken) return

    const { ok, message } = await claimStore(shopDomain, claimToken)

    if (ok) {
      onLinked()
      onClose()
    } else {
      // Surfaced inline — claimStore's message covers the common cases
      // ("no pending installation found", etc.).
      window.alert(message || 'Could not link the store. Please try again.')
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Connect your Shopify store</DialogTitle>
          <DialogDescription>
            {shopDomain ? (
              <>
                We found <b>{shopDomain}</b> already installed. Link it to your KSA Drop
                account to start syncing orders.
              </>
            ) : (
              <>
                Install KSA Drop from the Shopify App Store, or open <b>Apps</b> inside your
                Shopify Admin — your store links here automatically once it's installed.
              </>
            )}
          </DialogDescription>
        </DialogHeader>

        <div className='flex justify-end gap-2 pt-2'>
          <Button variant='outline' onClick={onClose}>
            Cancel
          </Button>
          {shopDomain ? (
            <Button onClick={handleLink} disabled={claiming || !claimToken}>
              {claiming ? (
                <Loader2 className='mr-2 h-4 w-4 animate-spin' />
              ) : (
                <Store className='mr-2 h-4 w-4' />
              )}
              Link Store
            </Button>
          ) : (
            appStoreUrl && (
              <Button asChild>
                <a href={appStoreUrl} target='_blank' rel='noopener noreferrer'>
                  <ExternalLink className='mr-2 h-4 w-4' />
                  Get it on the Shopify App Store
                </a>
              </Button>
            )
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
