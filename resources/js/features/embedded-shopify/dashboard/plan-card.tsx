import { useEffect, useState } from "react";
import { fetchBilling, startPlanPurchase, type BillingState } from "../api-client";

/**
 * The one-time plan. Approval happens on Shopify's confirmation page, so the
 * button leaves the iframe via the top window; Shopify returns the merchant to
 * the app, where fetchBilling() asks Shopify for the outcome.
 */
export function PlanCard() {
    const [billing, setBilling] = useState<BillingState | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetchBilling().then(setBilling).catch(() => undefined);
    }, []);

    if (!billing) return null;

    const price = new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: billing.plan.currency,
    }).format(billing.plan.price);

    async function purchase() {
        setBusy(true);
        setError(null);
        try {
            const result = await startPlanPurchase();
            if (result.confirmation_url) {
                window.open(result.confirmation_url, "_top");
                return;
            }
            setBilling(result);
        } catch (e) {
            setError((e as Error).message);
        }
        setBusy(false);
    }

    if (billing.purchased) {
        return (
            <s-section>
                <s-banner tone="success" heading="Pro plan is active">
                    <s-paragraph>
                        Thanks — your one-time {price} payment is complete. One-click product add
                        and all available products are included.
                    </s-paragraph>
                </s-banner>
            </s-section>
        );
    }

    return (
        <s-section heading="Free plan">
            <s-stack gap="base">
                {billing.status === "declined" && (
                    <s-banner tone="warning" heading="Purchase declined">
                        You declined the charge. You can try again whenever you're ready.
                    </s-banner>
                )}
                {error && <s-banner tone="critical">{error}</s-banner>}
                <s-paragraph>
                    <strong>Free:</strong> order sync only, with no one-click product add.
                </s-paragraph>
                <s-paragraph>
                    <strong>Pro:</strong> one-click product add, plus access to all products
                    available in the app.
                </s-paragraph>
                <s-paragraph>
                    Pro is a single {price} payment, billed once through Shopify. No recurring
                    charges.
                </s-paragraph>
                <s-button variant="primary" onClick={purchase} disabled={busy}>
                    {`Upgrade to Pro for ${price}`}
                </s-button>
            </s-stack>
        </s-section>
    );
}
