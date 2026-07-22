import { useEffect, useState } from 'react'
import { PORTAL_LOGIN_URL, buildPortalConnectUrl, getPortalConnectUrl } from './config'

/**
 * Shown when the store isn't linked to a KSA Drop account yet. Rather than a
 * dead-end error, guide the merchant to the portal. The link carries the shop
 * domain plus a signed claim token, so the portal opens the connect dialog
 * prefilled and verified — the merchant never types a myshopify.com URL, and
 * the link can't be reused for a different store.
 *
 * When the caller already resolved the connection state it passes `shop` and
 * `claimToken` so the link is built synchronously; otherwise the token is
 * fetched here.
 */
export function StoreNotConnected({
    heading,
    shop,
    claimToken,
}: {
    heading: string
    shop?: string
    claimToken?: string
}) {
    const [connectUrl, setConnectUrl] = useState(
        shop && claimToken ? buildPortalConnectUrl(shop, claimToken) : PORTAL_LOGIN_URL
    )

    useEffect(() => {
        if (shop && claimToken) return

        let cancelled = false

        getPortalConnectUrl().then((url) => {
            if (!cancelled) setConnectUrl(url)
        })

        return () => {
            cancelled = true
        }
    }, [shop, claimToken])

    return (
        <s-page heading={heading}>
            <s-section>
                <s-banner tone="warning" heading="Connect your store to get started">
                    <s-stack gap="base">
                        <s-paragraph>
                            This store isn't linked to a KSA Drop account yet. Click the
                            button below — after signing in to the KSA Drop portal, your
                            store will be ready to connect with one click.
                        </s-paragraph>
                        <s-button variant="primary" href={connectUrl} target="_blank">
                            Connect your store
                        </s-button>
                    </s-stack>
                </s-banner>
            </s-section>
        </s-page>
    )
}
