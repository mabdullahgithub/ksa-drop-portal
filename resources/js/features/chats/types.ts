/** Live chat, as the server sends it (App\Services\Chat\ChatPresenter). */

export type ChatStatus = 'open' | 'pending' | 'on_hold' | 'resolved' | 'closed'
export type ChatPriority = 'low' | 'normal' | 'high' | 'urgent'
export type ChatCategory =
  | 'order_issue'
  | 'shipment'
  | 'inventory'
  | 'billing'
  | 'account'
  | 'technical'
  | 'general'

/** Which screen this is: the team's inbox or the client's Support page. */
export type ChatSide = 'team' | 'client'

/** Who sent a message, as saved with it. The team also gets the account id and the role it had then. */
export interface ChatPerson {
  id?: number | null
  name: string
  avatar_url: string | null
  role?: string | null
}

export interface ChatOrderRef {
  id: number
  order_number: string
  status: string | null
  total?: string | number | null
  currency?: string | null
}

export interface ChatConversation {
  id: number
  reference: string
  subject: string
  category: ChatCategory
  status: ChatStatus
  opened_by: 'client' | 'staff'
  order: ChatOrderRef | null
  last_message: {
    body: string
    kind: 'message' | 'note'
    sender_type: 'client' | 'staff' | 'system'
    attachment_only: boolean
    voice: boolean
    shared: ChatSharedItem['type'] | null
    created_at: string | null
  } | null
  unread_count: number
  created_at: string | null
  last_message_at: string | null
  resolved_at: string | null
  closed_at: string | null
  // The team only:
  priority?: ChatPriority
  client?: {
    id: number
    company_name: string
    short_id: string | null
    status: string
    logo_url: string | null
    user_id: number
  } | null
  first_response_at?: string | null
  last_client_message_at?: string | null
  reopened_count?: number
}

export interface ChatAttachment {
  id: number
  name: string
  mime: string
  size: number
  is_image: boolean
  is_voice: boolean
  /** Seconds, for a voice message. */
  duration: number | null
  width: number | null
  height: number | null
  url: string
}

export interface ChatMessage {
  id: number
  conversation_id: number
  kind: 'message' | 'note' | 'event'
  internal: boolean
  sender_type: 'client' | 'staff' | 'system'
  sender: ChatPerson | null
  body: string | null
  meta: Record<string, any> | null
  client_uuid: string | null
  /** Orders and products the client attached, as they were then. */
  items: ChatSharedItem[]
  attachments: ChatAttachment[]
  created_at: string | null
  edited_at: string | null
  /** Browser-only: a message still on its way, or one that failed to send. */
  pending?: 'sending' | 'failed'
  /** Browser-only: files of a failed send, kept for the retry. */
  retryFiles?: File[]
  retryVoice?: VoiceClip
}

/** An order or inventory product attached to a message. */
export interface ChatSharedItem {
  type: 'order' | 'product'
  id: number
  /** The order number, or the product's name. */
  title: string
  // An order:
  status?: string | null
  total?: string | number | null
  currency?: string | null
  date?: string | null
  // A product:
  code?: string | null
  sku?: string | null
  quantity?: number
  out_of_stock?: boolean
  image_url?: string | null
}

/** Where the picker looks. A kind left out is one this person can't attach. */
export interface ChatItemSources {
  orders?: (q: string) => Promise<ChatSharedItem[]>
  products?: (q: string) => Promise<ChatSharedItem[]>
}

/** A recording that has not been sent yet. */
export interface VoiceClip {
  file: File
  seconds: number
  /** For listening to it before and while it is sent. */
  url: string
}

export interface ChatTeamDetail {
  conversation: ChatConversation
  client_read_up_to: number
  context: {
    client: {
      id: number
      company_name: string
      short_id: string | null
      status: string
      type_label: string
      contact_person: string | null
      phone: string | null
      email: string | null
      city: string | null
    } | null
    opened_by_name: string | null
    /** Recent changes and internal notes, newest first. */
    activity: ChatMessage[]
    other_conversations: Array<{
      id: number
      reference: string
      subject: string
      status: ChatStatus
      last_message_at: string | null
    }>
  }
  permissions: {
    reply: boolean
    note: boolean
    status: boolean
    edit: boolean
    delete: boolean
    share_orders: boolean
    share_products: boolean
  }
}

export interface ChatClientDetail {
  conversation: ChatConversation
  team_read_up_to: number
  can_share_inventory: boolean
  read_only: boolean
}

export interface ChatPage<T> {
  data: T[]
  next_cursor?: string | null
  has_more?: boolean
}

/** The number beside each option of the inbox dropdowns. */
export interface ChatFilterCounts {
  status: Record<string, number>
  priority: Record<string, number>
}

export interface ChatStats {
  open: number
  waiting_on_us: number
  resolved_today: number
  /** Over the last 7 days. */
  avg_response_minutes: number | null
}

export interface ChatOrderOption {
  id: number
  order_number: string
  status: string | null
  total: string | number | null
  currency: string | null
  created_at: string | null
}
