import DOMPurify from 'dompurify'

/**
 * Sanitise untrusted HTML (Shopify / CSV product descriptions) before it is
 * rendered with dangerouslySetInnerHTML. Strips scripts, event handlers and
 * javascript: URLs.
 */
export function sanitizeHtml(html: string | null | undefined): string {
  return html ? DOMPurify.sanitize(html) : ''
}
