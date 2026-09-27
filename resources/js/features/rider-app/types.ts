export type RiderBootMode = 'activate' | 'app' | 'sign_in'

/** window.__RIDER__, written by resources/views/rider.blade.php. */
export type RiderBoot = {
  mode: RiderBootMode
  reason?: string | null
  token?: string | null
  rider?: { name: string } | null
}

export type RiderAction = 'out_for_delivery' | 'delivered' | 'attempt_failed'

export type FailedReason =
  | 'no_answer'
  | 'refused'
  | 'wrong_address'
  | 'reschedule'
  | 'no_cash'
  | 'closed'
  | 'other'

export type PaymentMethod = 'cash' | 'card' | 'transfer'

export type BlockedReason = 'not_ksa_express' | 'finished' | 'held_by_other'

export type Parcel = {
  id: number
  tracking_number: string
  order_number: string | null
  status: string
  status_label: string
  receiver: {
    name: string | null
    phone: string | null
    address: string | null
    area: string | null
    city: string | null
    province: string | null
  }
  cod_amount: number
  currency: string
  items: { name: string; quantity: number }[]
  pieces: number
  remark: string | null
  attempts: number
  last_event: {
    action: RiderAction
    reason: FailedReason | null
    note: string | null
    occurred_at: string | null
  } | null
  held_by_me: boolean
  allowed_actions: RiderAction[]
  blocked: BlockedReason | null
  held_by: string | null
}

export type TodayStats = {
  held: number
  delivered: number
  failed: number
  cod_collected: number
}

export type Me = {
  rider: { name: string; phone: string; hub: string | null; photo_url: string | null }
  today: TodayStats
  /** WhatsApp help contact set by the admin; null until set. */
  support: { name: string | null; whatsapp: string; whatsapp_digits: string } | null
}

/** How the rider reached the update screen. */
export type EntryMethod = 'camera' | 'manual'

/** One of the rider's own updates (GET /rider/api/history). */
export type HistoryEvent = {
  id: number
  action: RiderAction
  reason: FailedReason | null
  cod_amount: number | null
  payment_method: PaymentMethod | null
  recipient_name: string | null
  occurred_at: string
  tracking_number: string | null
  order_number: string | null
  receiver_name: string | null
  city: string | null
  currency: string
}

export type HistoryRange = 'today' | 'yesterday' | 'week'

export type HistoryResponse = {
  summary: { count: number; cod_collected: number }
  events: HistoryEvent[]
}

/** One by one: scan, pick up, update. Batch: scan many at the hub, pick each up. */
export type ScanMode = 'single' | 'batch'

/** What a scan did (POST /rider/api/claim). */
export type ClaimResult = 'claimed' | 'already_mine' | 'held_by_other' | 'finished' | 'not_ksa_express' | 'not_found'

export type ClaimResponse = {
  result: ClaimResult
  parcel?: Parcel
  message?: string
}

/** A row in the batch pick-up list. */
export type BatchItem = {
  code: string
  state: 'working' | 'error' | ClaimResult
  parcel?: Parcel
}
