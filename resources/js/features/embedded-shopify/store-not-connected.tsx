import { useEffect, useState } from 'react'
import {
    PORTAL_LOGIN_URL,
    PORTAL_PRICING_URL,
    buildPortalConnectUrl,
    getPortalConnectUrl,
} from './config'

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
    installed = true,
}: {
    heading: string
    shop?: string
    claimToken?: string
    /** False when installation never completed — the portal claim cannot work yet. */
    installed?: boolean
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

    // Installation never completed, so there is no stored grant for the portal
    // to claim. Sending the merchant there would only produce a 404 — say what
    // actually went wrong instead.
    if (!installed) {
        return (
            <s-page heading={heading}>
                <s-section>
                    <s-banner tone="critical" heading="Setup could not be completed">
                        <s-paragraph>
                            We could not finish connecting to Shopify for this store. Please
                            uninstall and reinstall KSA Drop from the Shopify App Store. If
                            that doesn't help, contact KSA Drop support.
                        </s-paragraph>
                    </s-banner>
                </s-section>
            </s-page>
        )
    }

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

            {/*
             * Off-platform billing disclosure. KSA Drop charges for delivery,
             * cash-on-delivery collection, and warehousing outside the Shopify
             * Billing API, so the merchant is told before they connect a store
             * and start shipping — not after the first invoice.
             */}
            <s-section>
                <s-banner tone="info" heading="Shipping charges are billed outside of Shopify">
                    <s-stack gap="base">
                        <s-paragraph>
                            The KSA Drop app is free to install and use. Delivery,
                            cash-on-delivery collection, and warehousing are paid services
                            invoiced directly by KSA Drop — they are not charged through
                            Shopify and will not appear on your Shopify bill. You receive a
                            written rate card before your account is opened, and nothing is
                            charged until your first shipment is picked up.
                        </s-paragraph>
                        <s-button href={PORTAL_PRICING_URL} target="_blank">
                            View pricing and billing details
                        </s-button>
                    </s-stack>
                </s-banner>
            </s-section>
        </s-page>
    )
}
