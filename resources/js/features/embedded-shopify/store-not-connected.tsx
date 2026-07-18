import { PORTAL_CONNECT_URL } from './config'

/**
 * Shown when the embedded API returns 401 — the store isn't linked to a
 * KSA Drop account yet. Rather than a dead-end error, guide the merchant to
 * the portal. The link carries the shop domain, so the portal opens the
 * connect dialog prefilled — the merchant never types a myshopify.com URL.
 */
export function StoreNotConnected({ heading }: { heading: string }) {
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
                        <s-button variant="primary" href={PORTAL_CONNECT_URL} target="_blank">
                            Connect your store
                        </s-button>
                    </s-stack>
                </s-banner>
            </s-section>
        </s-page>
    )
}
