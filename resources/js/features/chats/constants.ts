import type { ChatCategory, ChatPriority, ChatStatus } from './types'

/** Status labels for each side: the client reads them from where they stand. */
export const STATUS_META: Record<
  ChatStatus,
  { label: string; clientLabel: string; dot: string }
> = {
  open: {
    label: 'Open',
    clientLabel: 'Waiting for support',
    dot: 'bg-sky-500',
  },
  pending: {
    label: 'Waiting on client',
    clientLabel: 'Awaiting your reply',
    dot: 'bg-amber-500',
  },
  on_hold: {
    label: 'On hold',
    clientLabel: 'In progress',
    dot: 'bg-violet-500',
  },
  resolved: {
    label: 'Resolved',
    clientLabel: 'Resolved',
    dot: 'bg-emerald-500',
  },
  closed: {
    label: 'Closed',
    clientLabel: 'Closed',
    dot: 'bg-muted-foreground/50',
  },
}

export const STATUS_ORDER: ChatStatus[] = ['open', 'pending', 'on_hold', 'resolved', 'closed']

/** Where the team may move a chat from each status (ChatWorkflow::TEAM_MOVES). */
export const TEAM_MOVES: Record<ChatStatus, ChatStatus[]> = {
  open: ['pending', 'on_hold', 'resolved', 'closed'],
  pending: ['open', 'on_hold', 'resolved', 'closed'],
  on_hold: ['open', 'pending', 'resolved', 'closed'],
  resolved: ['open', 'closed'],
  closed: ['open'],
}

export const CATEGORY_LABELS: Record<ChatCategory, string> = {
  order_issue: 'Order issue',
  shipment: 'Shipment & delivery',
  inventory: 'Inventory & stock',
  billing: 'Billing & payments',
  account: 'Account',
  technical: 'Technical problem',
  general: 'General question',
}

export const PRIORITY_META: Record<ChatPriority, { label: string; className: string }> = {
  low: { label: 'Low', className: 'text-muted-foreground' },
  normal: { label: 'Normal', className: 'text-foreground' },
  high: { label: 'High', className: 'text-orange-600 dark:text-orange-400' },
  urgent: { label: 'Urgent', className: 'text-red-600 dark:text-red-400' },
}

export const MAX_FILES = 5
/** config/chat.php: chat.max_shared_items */
export const MAX_SHARED_ITEMS = 5
/** config/chat.php: chat.voice.max_seconds */
export const MAX_VOICE_SECONDS = 180
export const MAX_FILE_MB = 10
export const ACCEPT_FILES = '.jpg,.jpeg,.png,.webp,.gif,.pdf,.csv,.txt,.xlsx,.xls,.docx,.doc'
export const MAX_BODY = 5000
