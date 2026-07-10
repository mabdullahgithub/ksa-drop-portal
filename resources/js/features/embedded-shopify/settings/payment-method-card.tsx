import type { FilterCardProps } from './settings-page'
import type { SyncFilters } from '../api-client'

const OPTIONS: { value: SyncFilters['payment_method']; label: string; details: string }[] = [
    { value: 'all', label: 'All payment methods', details: 'Process every order.' },
    { value: 'cod', label: 'Cash on delivery (COD) only', details: 'Skip prepaid orders.' },
    { value: 'prepaid', label: 'Prepaid only', details: 'Skip cash on delivery orders.' },
]

export function PaymentMethodCard({ filters, onChange }: FilterCardProps) {
    return (
        <s-section heading="Payment method">
            <s-choice-list
                label="Which orders should be processed?"
                labelAccessibilityVisibility="exclusive"
                values={[filters.payment_method]}
                onChange={(event: Event) => {
                    const values =
                        (event.currentTarget as HTMLElement & { values?: string[] }).values ?? []
                    onChange({
                        ...filters,
                        payment_method:
                            (values[0] as SyncFilters['payment_method']) ?? 'all',
                    })
                }}
            >
                {OPTIONS.map((option) => (
                    <s-choice key={option.value} value={option.value} details={option.details}>
                        {option.label}
                    </s-choice>
                ))}
            </s-choice-list>
        </s-section>
    )
}
