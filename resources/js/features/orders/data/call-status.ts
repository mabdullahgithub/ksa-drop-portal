import type { CallStatus, WhatsAppStatus } from '@/types/order'

export const CALL_STATUS_OPTIONS: {
  value: CallStatus
  label: string
  className: string
}[] = [
  { value: 'not_called', label: 'Not Called', className: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400' },
  { value: 'no_answer', label: 'No Answer', className: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400' },
  { value: 'confirmed', label: 'Confirmed', className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' },
  { value: 'cancelled', label: 'Cancelled', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
  { value: 'wrong_number', label: 'Wrong Number', className: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' },
]

export const callStatusLabel = (status?: CallStatus | null) =>
  CALL_STATUS_OPTIONS.find((o) => o.value === status)?.label ?? 'Not Called'

export const callStatusClass = (status?: CallStatus | null) =>
  CALL_STATUS_OPTIONS.find((o) => o.value === status)?.className ?? CALL_STATUS_OPTIONS[0].className

/**
 * Conversation flow state, not delivery state — "sent" here means the message
 * left the portal, and says nothing about whether it reached the phone. The
 * delivered/read timestamps on the order carry that separately.
 */
export const WHATSAPP_STATUS_META: Record<WhatsAppStatus, { label: string; className: string }> = {
  sent: { label: 'Awaiting reply', className: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' },
  followup_sent: { label: 'Followed up', className: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400' },
  replied: { label: 'Replied', className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' },
  confirmed: { label: 'Confirmed', className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' },
  graveyard: { label: 'Graveyard', className: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400' },
  failed: { label: 'Failed', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
}
