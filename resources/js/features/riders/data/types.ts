/**
 * What the person does with the app. A rider takes parcels out and delivers
 * them; an inventory manager stays at the warehouse and scans parcels OUT
 * and IN, which moves stock.
 */
export const RIDER_ROLES = [
  { value: 'rider', label: 'Rider', hint: 'Takes parcels out, delivers them and collects the cash.' },
  {
    value: 'inventory_manager',
    label: 'Inventory manager',
    hint: 'Stays at the warehouse and scans parcels OUT and IN, which moves stock. Cannot take or deliver parcels.',
  },
] as const

export type RiderRole = (typeof RIDER_ROLES)[number]['value']

/** What an inventory manager scanned today, each way. */
export type StockToday = Record<'out' | 'in', { parcels: number; pieces: number }>

export type RiderRow = {
  id: number
  name: string
  role: RiderRole
  name_ar: string | null
  photo_url: string | null
  phone: string
  phone_local: string
  national_id: string | null
  nationality: string | null
  vehicle_type: string | null
  vehicle_plate: string | null
  license_number: string | null
  license_expiry: string | null
  warehouse_id: number | null
  warehouse_name: string | null
  city: string | null
  employment_type: string | null
  iban: string | null
  /** What KSA Drop pays this rider per delivered order and per attempt; null until set. */
  delivery_rate: number | null
  attempt_rate: number | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  notes: string | null
  status: 'active' | 'suspended'
  has_pin: boolean
  pin_locked: boolean
  has_pending_link: boolean
  device: {
    platform: 'android' | 'ios' | 'other' | null
    label: string | null
    sign_in_method: 'activation' | 'pin'
    standalone: boolean
    signed_in_at: string | null
    last_seen_at: string | null
  } | null
  last_seen_at: string | null
  /** The app is open on their phone right now (checked in within 2 minutes). */
  online: boolean
  /**
   * A parcel counts once, by where it stands now. `held` is everything in the
   * rider's hands, including `failed` (waiting for another try) and
   * `to_return` (returned or cancelled, not handed back yet). The rest is
   * today's; `cash_collected` is the part of the COD paid in cash.
   */
  stats: { held: number; delivered: number; failed: number; to_return: number; cod_collected: number; cash_collected: number }
  cash: RiderCash
  pay: RiderPay
  /** An inventory manager's scans today; null for a rider. */
  stock_today: StockToday | null
  created_at: string | null
}

/**
 * What KSA Drop owes a rider: the delivery rate per delivered order, the
 * attempt rate per order they went for and the customer didn't take, minus
 * what was paid out.
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
  paid_by: Record<RiderPaymentMethod, number>
  /** Above zero: still to pay the rider. Below zero: paid more than earned. */
  balance: number
  /** Orders paid as delivered, and as an attempt. */
  delivered: number
  attempted: number
}

/** `in`: COD cash the rider hands in. `out`: KSA Drop paying the rider. */
export type PaymentDirection = 'in' | 'out'

/** What a rider owes: all the COD they took (cash, card or transfer), minus what they handed in. `collected` is the cash part. */
export type RiderCash = {
  collected: number
  /** COD paid by card or transfer. Owed like the cash. */
  direct: number
  /** `direct`, by how the customer paid. */
  card: number
  transfer: number
  paid: number
  /** Above zero: still owed. Below zero: handed in more than collected. */
  balance: number
}

export const PAYMENT_METHODS = [
  { value: 'cash', label: 'Cash' },
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'other', label: 'Other' },
] as const

export type RiderPaymentMethod = (typeof PAYMENT_METHODS)[number]['value']

/** Money a rider handed in. A voided one stays listed but no longer counts. */
export type RiderPayment = {
  id: number
  direction: PaymentDirection
  amount: number
  method: RiderPaymentMethod
  reference: string | null
  note: string | null
  received_at: string
  recorded_by: string | null
  created_at: string | null
  voided_at: string | null
  voided_by: string | null
  void_reason: string | null
}

export type RiderBalances = { cash: RiderCash; pay: RiderPay }

export type RiderPaymentsPage = RiderBalances & { payments: RiderPayment[]; next_page: number | null }

export type WarehouseOption = { id: number; name: string; is_default: boolean }

/** The WhatsApp contact riders see on their Profile screen. */
export type RiderSupportContact = { whatsapp: string; whatsapp_local: string; name: string | null }

export const VEHICLE_TYPES = [
  { value: 'motorcycle', label: 'Motorcycle' },
  { value: 'car', label: 'Car' },
  { value: 'van', label: 'Van' },
  { value: 'bicycle', label: 'Bicycle' },
  { value: 'other', label: 'Other' },
] as const

export const EMPLOYMENT_TYPES = [
  { value: 'staff', label: 'Staff' },
  { value: 'freelancer', label: 'Freelancer' },
  { value: 'agency', label: 'Agency' },
] as const

/** One rider's totals in the Top performers leaderboard. */
export type RiderPerformanceRow = {
  id: number
  name: string
  photo_url: string | null
  warehouse_name: string | null
  status: 'active' | 'suspended'
  /** Parcels the rider scanned out, delivered or attempted in the range. */
  assigned: number
  delivered: number
  /** Failed attempts (a parcel can fail more than once). */
  failed: number
  cod_collected: number
}

/** Numbers per KSA day, lined up with `RiderPerformance.days`. */
export type DailySeries = { delivered: number[]; failed: number[]; cod_collected: number[] }

/** The whole team per day; `riders` is how many made an attempt that day. */
export type TeamDaily = DailySeries & { riders: number[] }

/** Where a parcel the rider handled ended up. */
export type ParcelOutcome = 'delivered' | 'out_for_delivery' | 'attempt_fail' | 'cancelled' | 'returned' | 'handed_back' | 'other'

export type RiderParcelEvent = {
  action: 'out_for_delivery' | 'delivered' | 'attempt_failed' | 'returned' | 'cancelled' | 'returned_to_hub'
  occurred_at: string | null
  /** Why it failed, was returned or was cancelled, already worded. */
  reason: string | null
  /** The day the customer asked for, `yyyy-MM-dd`, when they put the delivery off. */
  reschedule_date: string | null
  note: string | null
  cod_amount: number | null
  payment_method: string | null
  /** Proof: the rider's photo (of the delivery, or of the place on a failed attempt) and where the phone was. */
  photo_url: string | null
  lat: number | null
  lng: number | null
}

export type RiderParcel = {
  id: number
  tracking_number: string | null
  order_number: string | null
  city: string | null
  cod_amount: number
  status: string
  status_label: string
  outcome: ParcelOutcome
  /** Handed back: the rider who has it now, or null when an admin unassigned it. */
  held_by: string | null
  cancel_reason: string | null
  cancelled_at: string | null
  /** Returned or cancelled, and the rider hasn't handed it back at the hub yet. */
  awaiting_hand_back: boolean
  hub_received_at: string | null
  /** This rider's updates on the parcel in the range, oldest first. */
  events: RiderParcelEvent[]
}

/** One rider in the range — the details under the leaderboard. */
export type RiderSummary = {
  assigned: number
  outcomes: Record<ParcelOutcome, number>
  failed_reasons: { reason: string; label: string; count: number }[]
  daily: DailySeries
}

/** One page of a rider's parcel list, most recently touched first. */
export type RiderParcelsPage = { parcels: RiderParcel[]; next_page: number | null }

export type RiderPerformance = {
  from: string
  to: string
  /** Every KSA day in the range, `yyyy-MM-dd`. */
  days: string[]
  /** Riders who made an update in the range, most deliveries first. */
  riders: RiderPerformanceRow[]
  team_daily: TeamDaily
}
