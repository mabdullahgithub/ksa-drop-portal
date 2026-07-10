import { useState } from 'react'
import type { FilterCardProps } from './settings-page'

export function TagFilterCard({ filters, onChange }: FilterCardProps) {
    return (
        <s-section heading="Order tags">
            <s-stack gap="base">
                <s-paragraph>
                    Filter orders by their Shopify tags. Leave empty to ignore tags.
                </s-paragraph>

                <TagInput
                    label="Only process orders with at least one of these tags"
                    tags={filters.tags_include}
                    onChange={(tags) => onChange({ ...filters, tags_include: tags })}
                />

                <TagInput
                    label="Never process orders with any of these tags"
                    tags={filters.tags_exclude}
                    onChange={(tags) => onChange({ ...filters, tags_exclude: tags })}
                />
            </s-stack>
        </s-section>
    )
}

function TagInput({
    label,
    tags,
    onChange,
}: {
    label: string
    tags: string[]
    onChange: (tags: string[]) => void
}) {
    const [draft, setDraft] = useState('')

    const addTag = () => {
        const tag = draft.trim()
        if (tag && !tags.some((t) => t.toLowerCase() === tag.toLowerCase())) {
            onChange([...tags, tag])
        }
        setDraft('')
    }

    return (
        <s-stack gap="small-200">
            <s-stack direction="inline" gap="small-200" alignItems="end">
                <s-text-field
                    label={label}
                    value={draft}
                    placeholder="Enter a tag and press Add"
                    onInput={(event: Event) =>
                        setDraft((event.currentTarget as HTMLInputElement).value)
                    }
                    onKeyDown={(event: KeyboardEvent) => {
                        if (event.key === 'Enter') addTag()
                    }}
                />
                <s-button onClick={addTag} disabled={!draft.trim()}>
                    Add
                </s-button>
            </s-stack>

            {tags.length > 0 && (
                <s-stack direction="inline" gap="small-200">
                    {tags.map((tag) => (
                        <s-chip
                            key={tag}
                            removable
                            onRemove={() => onChange(tags.filter((t) => t !== tag))}
                        >
                            {tag}
                        </s-chip>
                    ))}
                </s-stack>
            )}
        </s-stack>
    )
}
