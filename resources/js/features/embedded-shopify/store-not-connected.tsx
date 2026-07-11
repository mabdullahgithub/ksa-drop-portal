import { PORTAL_LOGIN_URL } from './config'

/**
 * Shown when the embedded API returns 401 — the store isn't linked to a
 * KSA Drop account yet. Rather than a dead-end error, guide the merchant to
 * the portal to connect their store.
 */
export function StoreNotConnected({ heading }: { heading: string }) {
    return (
        <s-page heading={heading}>
            <s-section>
                <s-banner tone="warning" heading="Connect your store to get started">
                    <s-stack gap="base">
                        <s-paragraph>
                            This store isn't linked to a KSA Drop account yet. Sign in to the
                            KSA Drop portal, then connect this store from Connectors → Connect
                            Shopify to start syncing orders.
                        </s-paragraph>
                        <s-button variant="primary" href={PORTAL_LOGIN_URL} target="_blank">
                            Connect your store
                        </s-button>
                    </s-stack>
                </s-banner>
            </s-section>
        </s-page>
    )
}
