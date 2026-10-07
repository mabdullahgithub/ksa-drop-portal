export type RiderBootMode = 'activate' | 'app' | 'sign_in'

/**
 * Who the app is for. A rider takes parcels out and delivers them; an
 * inventory manager stays at the warehouse and scans parcels OUT and IN.
 */
export type RiderRole = 'rider' | 'inventory_manager'

/** window.__RIDER__, written by resources/views/rider.blade.php. */
export type RiderBoot = {
  mode: RiderBootMode
  reason?: string | null
  token?: string | null
  /** `role` is there once signed in: it picks which app to show. */
  rider?: { name: string; role?: RiderRole } | null
  /** The build this page was served with (RiderAppController::build()). */
  build?: string | null
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
    /** The day the customer asked for, `yyyy-MM-dd`, when they put the delivery off. */
    reschedule_date: string | null
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
  /** `direct`, by how the customer paid. */
  card: number
  transfer: number
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
  /** `earned`, by what earned it. */
  earned_delivered: number
  earned_attempted: number
  /** `earned`, by where it came from: deliveries by how the customer paid (prepaid: nothing to collect), and failed attempts. */
  earned_by: Record<'cash' | 'card' | 'transfer' | 'prepaid' | 'attempt', { amount: number; count: number }>
  paid: number
  /** `paid`, by how KSA Drop paid it. */
  paid_by: Record<CashPaymentMethod, number>
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
  rider: { name: string; role: RiderRole; phone: string; hub: string | null; photo_url: string | null }
  today: TodayStats
  cash: RiderCash
  pay: RiderPay
  /** WhatsApp help contact set by the admin; null until set. */
  support: { name: string | null; whatsapp: string; whatsapp_digits: string } | null
  /** The build live on the server now; differs from the page's after a deploy. */
  build?: string | null
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

/** OUT: the parcel leaves the warehouse and its items come off stock. IN: it comes back and they go back on. */
export type StockDirection = 'out' | 'in'

/** The parcel a stock scan was made on, whichever courier carries it. */
export type StockParcel = {
  id: number
  tracking_number: string | null
  order_number: string | null
  courier: string
  courier_label: string
  status: string
  status_label: string
  receiver_name: string | null
  city: string | null
}

/** One item in the parcel. Not matched: it is linked to no product, so no stock changed for it. */
export type StockItem = { name: string; sku: string | null; quantity: number; matched: boolean; stock_after: number | null }

/** One parcel scanned OUT or IN (POST /rider/api/stock/scan, GET /rider/api/stock). */
export type StockScan = {
  id: number
  direction: StockDirection
  occurred_at: string
  /** Units in the parcel. */
  pieces: number
  /** Items that matched no product. */
  unmatched: number
  scanned_by: string | null
  /** The rider who had the parcel when it was scanned. */
  parcel_rider: string | null
  items: StockItem[]
  parcel: StockParcel | null
}

/** The direction itself: stock moved. Anything else changed nothing. */
export type StockScanResult = StockDirection | 'already_out' | 'already_in' | 'finished' | 'not_found'

export type StockScanResponse = {
  result: StockScanResult
  scan?: StockScan
  parcel?: StockParcel
  message?: string
}

/** GET /rider/api/stock: what I scanned today, each way. */
export type StockHome = {
  today: Record<StockDirection, { parcels: number; pieces: number }>
}

/** GET /rider/api/stock/scans: one page of my scans, newest first. `next_page` is null on the last one. */
export type StockScansPage = { scans: StockScan[]; next_page: number | null }

/** A row in the live list over the camera. */
export type StockSessionItem = {
  code: string
  state: 'working' | 'error' | StockScanResult
  scan?: StockScan
  parcel?: StockParcel
}
