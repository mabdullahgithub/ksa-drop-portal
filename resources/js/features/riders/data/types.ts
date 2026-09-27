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
