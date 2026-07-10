import type { FilterCardProps } from './settings-page'

const FINANCIAL_STATUSES = [
    { value: 'paid', label: 'Paid' },
    { value: 'pending', label: 'Pending' },
    { value: 'partially_paid', label: 'Partially paid' },
    { value: 'refunded', label: 'Refunded' },
    { value: 'partially_refunded', label: 'Partially refunded' },
    { value: 'voided', label: 'Voided' },
]

const FULFILLMENT_STATUSES = [
    { value: 'unfulfilled', label: 'Unfulfilled' },
    { value: 'fulfilled', label: 'Fulfilled' },
    { value: 'cancelled', label: 'Cancelled' },
]

/**
 * s-choice-list emits a change Event whose currentTarget carries `values`
 * (the selected choice values) — read it off the element, not per-choice.
 */
function selectedValues(event: Event): string[] {
    return ((event.currentTarget as HTMLElement & { values?: string[] }).values ?? [])
}

export function StatusFilterCard({ filters, onChange }: FilterCardProps) {
    return (
        <s-section heading="Order status">
            <s-stack gap="base">
                <s-paragraph>
                    Only process orders with the selected statuses. Leave all unchecked to
                    process every status.
                </s-paragraph>

                <s-choice-list
                    label="Payment status"
                    multiple
                    values={filters.financial_statuses}
                    onChange={(event: Event) =>
                        onChange({ ...filters, financial_statuses: selectedValues(event) })
                    }
                >
                    {FINANCIAL_STATUSES.map((status) => (
                        <s-choice key={status.value} value={status.value}>
                            {status.label}
                        </s-choice>
                    ))}
                </s-choice-list>

                <s-choice-list
                    label="Fulfillment status"
                    multiple
                    values={filters.fulfillment_statuses}
                    onChange={(event: Event) =>
                        onChange({ ...filters, fulfillment_statuses: selectedValues(event) })
                    }
                >
                    {FULFILLMENT_STATUSES.map((status) => (
                        <s-choice key={status.value} value={status.value}>
                            {status.label}
                        </s-choice>
                    ))}
                </s-choice-list>
            </s-stack>
        </s-section>
    )
}
