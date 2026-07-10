/**
 * JSX typings for the Shopify Polaris web components used by the embedded
 * app. The components themselves ship at runtime via
 * https://cdn.shopify.com/shopifycloud/polaris.js (loaded in
 * resources/views/embedded.blade.php) — no npm package is bundled.
 * @shopify/polaris-types only augments Preact's JSX namespace, so the React
 * declarations live here, intentionally permissive.
 */
import type * as React from 'react'

// Polaris custom elements dispatch native DOM events, not React synthetic
// events, so the React handler typings are replaced with native ones.
type PolarisElementProps = Omit<
    React.DetailedHTMLProps<React.HTMLAttributes<HTMLElement>, HTMLElement>,
    'onChange' | 'onInput' | 'onKeyDown' | 'onClick'
> & {
    onChange?: (event: Event) => void
    onInput?: (event: Event) => void
    onKeyDown?: (event: KeyboardEvent) => void
    onClick?: (event: Event) => void
    [key: string]: unknown
}

declare module 'react' {
    namespace JSX {
        interface IntrinsicElements {
            's-page': PolarisElementProps
            's-section': PolarisElementProps
            's-grid': PolarisElementProps
            's-stack': PolarisElementProps
            's-box': PolarisElementProps
            's-divider': PolarisElementProps
            's-heading': PolarisElementProps
            's-paragraph': PolarisElementProps
            's-text': PolarisElementProps
            's-badge': PolarisElementProps
            's-banner': PolarisElementProps
            's-button': PolarisElementProps
            's-spinner': PolarisElementProps
            's-chip': PolarisElementProps
            's-choice-list': PolarisElementProps
            's-choice': PolarisElementProps
            's-text-field': PolarisElementProps
            's-table': PolarisElementProps
            's-table-header-row': PolarisElementProps
            's-table-header': PolarisElementProps
            's-table-body': PolarisElementProps
            's-table-row': PolarisElementProps
            's-table-cell': PolarisElementProps
        }
    }
}

declare global {
    /** App Bridge v4 global, provided by the app-bridge.js CDN script. */
    interface Window {
        shopify: {
            idToken: () => Promise<string>
            toast: {
                show: (message: string, opts?: { isError?: boolean; duration?: number }) => void
            }
            config: { shop?: string; host?: string; apiKey?: string }
        }
    }
}

export {}
