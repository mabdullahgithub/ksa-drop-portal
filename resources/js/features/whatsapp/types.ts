import type { CallStatus, WhatsAppStatus } from '@/types/order'

export interface ConversationSummary {
  id: number
  order_number: string
  customer_name: string | null
  phone: string | null
  client_name: string | null
  total: string
  currency: string

  whatsapp_status: WhatsAppStatus
  call_status: CallStatus
  tags: string[] | null

  sent_at: string | null
  followup_sent_at: string | null
  replied_at: string | null
  delivered_at: string | null
  read_at: string | null

  last_message: string | null
  last_message_at: string | null
  last_message_direction: 'outbound' | 'inbound' | null
  last_outbound_status: string | null
  message_count: number

  needs_attention: boolean
}

export interface ThreadMessage {
  id: number
  direction: 'outbound' | 'inbound'
  body: string | null
  template_key: string | null
  status: string | null
  created_at: string
  sent_at: string | null
  delivered_at: string | null
  read_at: string | null
  failed_at: string | null
  error_code: string | null
  error_message: string | null
}

export interface ConversationOrder {
  id: number
  order_number: string
  customer_name: string | null
  customer_phone: string | null
  customer_email: string | null
  shipping_name: string | null
  shipping_address1: string | null
  shipping_address2: string | null
  shipping_city: string | null
  shipping_province: string | null
  shipping_country: string | null
  total: string
  currency: string
  payment_method: string | null
  is_cod: boolean
  financial_status: string
  fulfillment_status: string
  call_status: CallStatus
  call_attempts: number
  call_notes: string | null
  last_called_at: string | null
  tags: string[] | null
  created_at: string
  client: { id: number; company_name: string } | null
  items: { id: number; name: string; quantity: number; price: string }[]
  tracking_number: string | null
}

export interface ConversationDetail {
  conversation: ConversationSummary
  messages: ThreadMessage[]
  order: ConversationOrder
}

export interface InboxStats {
  all: number
  needs_attention: number
  sent: number
  followup_sent: number
  replied: number
  confirmed: number
  graveyard: number
  failed: number
  delivered: number
  read: number
}

export const INBOX_FILTERS: { value: string; label: string; statKey: keyof InboxStats }[] = [
  { value: 'all', label: 'All', statKey: 'all' },
  { value: 'needs_attention', label: 'Needs action', statKey: 'needs_attention' },
  { value: 'sent', label: 'Awaiting', statKey: 'sent' },
  { value: 'followup_sent', label: 'Followed up', statKey: 'followup_sent' },
  { value: 'confirmed', label: 'Confirmed', statKey: 'confirmed' },
  { value: 'graveyard', label: 'Graveyard', statKey: 'graveyard' },
  { value: 'failed', label: 'Failed', statKey: 'failed' },
]
