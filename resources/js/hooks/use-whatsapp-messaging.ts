import { usePage } from '@inertiajs/react'
import type { PageProps } from '@/types'

/**
 * Whether WhatsApp messaging is switched on (the toggle on the WhatsApp page).
 *
 * While it is off, screens hide everything WhatsApp added -- the call and
 * WhatsApp columns, the call-outcome panel, the bulk call-status action, the
 * dashboard section, the Apps card -- so the app looks as it did before.
 */
export function useWhatsAppMessaging(): boolean {
  return usePage<PageProps>().props.whatsappMessaging ?? false
}
