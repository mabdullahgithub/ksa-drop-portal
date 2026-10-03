export type RiderBootMode = 'activate' | 'app' | 'sign_in'

/** window.__RIDER__, written by resources/views/rider.blade.php. */
export type RiderBoot = {
  mode: RiderBootMode
  reason?: string | null
  token?: string | null
  rider?: { name: string } | null
}

/**
 * returned / cancelled end the delivery; the parcel then stays with the rider
 * until they hand it back at the hub (returned_to_hub).
 */
export type RiderAction = 'out_for_delivery' | 'delivered' | 'attempt_failed' | 'returned' | 'cancelled' | 'returned_to_hub'

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
  /** Returned or cancelled and still with me: only to hand back at the hub. */
  to_return: boolean
  allowed_actions: RiderAction[]
  blocked: BlockedReason | null
  held_by: string | null
}

/** A parcel counts once, by where it stands now. */
export type TodayStats = {
  /** Everything in my hands, including `failed` and `to_return`. */
  held: number
  delivered: number
  /** With me, waiting for another try. */
  failed: number
  /** With me, to hand back at the hub. */
  to_return: number
  /** COD taken today, however it was paid. */
  cod_collected: number
  /** The part of it paid in cash — what I have to hand in. */
  cash_collected: number
}

/** What I owe KSA Drop: cash collected minus what I handed in. */
export type RiderCash = {
  collected: number
  /** COD paid by card or transfer: it reached KSA Drop directly, never owed. */
  direct: number
  paid: number
  /** Above zero: still to pay. Below zero: paid more than collected. */
  balance: number
}

export type CashPaymentMethod = 'cash' | 'bank_transfer' | 'other'

/** One payment KSA Drop recorded: from me (cash handed in) or to me (my pay). */
export type CashPayment = {
  id: number
  amount: number
  method: CashPaymentMethod
  reference: string | null
  received_at: string
}

/**
 * What KSA Drop owes me: the delivery rate for each delivery and the attempt
 * rate for each visit the customer didn't take the parcel on, minus what it
 * has paid me.
 */
export type RiderPay = {
  earned: number
  paid: number
  /** Above zero: still to be paid to me. Below zero: paid more than earned. */
  balance: number
  /** Visits paid as a delivery, and as an attempt. */
  delivered: number
  attempted: number
  /** What a delivery and an attempt pay me now. */
  rates: { delivery: number; attempt: number }
}

/** GET /rider/api/cash: both accounts, each with its payments, newest first. */
export type CashResponse = { cash: RiderCash; payments: CashPayment[]; pay: RiderPay; payouts: CashPayment[] }

export type Me = {
  rider: { name: string; phone: string; hub: string | null; photo_url: string | null }
  today: TodayStats
  cash: RiderCash
  pay: RiderPay
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

/** A ready-made period, or 'custom': two dates the rider picked. */
export type HistoryRange = 'today' | 'yesterday' | 'week' | 'custom'

/** KSA days as yyyy-MM-dd, both included. */
export type HistoryDates = { from: string; to: string }

export type HistoryResponse = {
  /** The whole period; `events` stops at the newest 500. The cash figures are what was paid in cash. */
  summary: { count: number; cod_collected: number; cash_count: number; cash_collected: number }
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
