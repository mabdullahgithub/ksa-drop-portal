export type RiderRow = {
  id: number
  name: string
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
  stats: { held: number; delivered: number; failed: number; cod_collected: number }
  created_at: string | null
}

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
  action: 'out_for_delivery' | 'delivered' | 'attempt_failed'
  occurred_at: string | null
  /** Failed-attempt reason, already worded. */
  reason: string | null
  note: string | null
  cod_amount: number | null
  payment_method: string | null
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
