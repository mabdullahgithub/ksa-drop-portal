export type PartnerType = 'client' | 'agency' | 'influencer'
export type PartnerStatus = 'pending' | 'approved' | 'rejected' | 'suspended'
export type PartnerTier = 'bronze' | 'silver' | 'gold'

export type ProgramSummary = {
  approval_bonus_points: number
  activation_points: number
  delivery_points: number
  delivery_months: number
  milestone_deliveries: number
  milestone_points: number
  min_redeem_points: number
  tiers: { name: string; min_active_clients: number; multiplier: number }[]
  terms_version: string
}

export type PartnerSummary = {
  id: number
  code: string
  code_can_change: boolean
  previous_codes: string[]
  display_name: string
  type: PartnerType
  status: PartnerStatus
  tier: PartnerTier
  points_balance: number
  referral_url: string
  logo_url: string | null
  is_client: boolean
}

export type PointTransaction = {
  id: number
  type: string
  points: number
  description: string | null
  created_at: string
}

export type PayoutMethod = {
  type: 'bank' | 'other'
  bank_name?: string | null
  holder_name?: string | null
  iban?: string | null
  details?: string | null
} | null

export type PayoutRequestRow = {
  id: number
  points: number
  status: 'requested' | 'approved' | 'paid' | 'rejected'
  partner_note: string | null
  admin_note: string | null
  amount_paid: string | number | null
  currency: string | null
  paid_at: string | null
  created_at: string
  method: string | null
}

export const PARTNER_TYPE_LABEL: Record<PartnerType, string> = {
  client: 'Seller referral',
  agency: 'Agency / freelancer',
  influencer: 'Influencer / community',
}

export const TIER_LABEL: Record<PartnerTier, string> = {
  bronze: 'Bronze',
  silver: 'Silver',
  gold: 'Gold',
}

export const TRANSACTION_LABEL: Record<string, string> = {
  approval_bonus: 'Welcome bonus',
  activation: 'Referral activated',
  delivery: 'Delivery',
  milestone: 'Milestone',
  adjustment: 'Adjustment',
  payout: 'Payout',
  payout_reversal: 'Payout reversed',
}

export const AUDIENCE_SIZES = [
  { value: 'under_1k', label: 'Under 1,000' },
  { value: '1k_10k', label: '1,000 – 10,000' },
  { value: '10k_50k', label: '10,000 – 50,000' },
  { value: '50k_200k', label: '50,000 – 200,000' },
  { value: 'over_200k', label: 'Over 200,000' },
  { value: 'na', label: 'Not applicable' },
]

export const SOCIAL_KEYS = ['instagram', 'tiktok', 'youtube', 'snapchat', 'x', 'linkedin', 'telegram', 'other'] as const

export const SOCIAL_LABEL: Record<(typeof SOCIAL_KEYS)[number], string> = {
  instagram: 'Instagram',
  tiktok: 'TikTok',
  youtube: 'YouTube',
  snapchat: 'Snapchat',
  x: 'X (Twitter)',
  linkedin: 'LinkedIn',
  telegram: 'Telegram / WhatsApp group',
  other: 'Other',
}

export const COUNTRIES = [
  { value: 'SA', label: 'Saudi Arabia' },
  { value: 'AE', label: 'United Arab Emirates' },
  { value: 'KW', label: 'Kuwait' },
  { value: 'BH', label: 'Bahrain' },
  { value: 'QA', label: 'Qatar' },
  { value: 'OM', label: 'Oman' },
  { value: 'EG', label: 'Egypt' },
  { value: 'JO', label: 'Jordan' },
  { value: 'PK', label: 'Pakistan' },
  { value: 'IN', label: 'India' },
  { value: 'BD', label: 'Bangladesh' },
  { value: 'TR', label: 'Turkey' },
  { value: 'MA', label: 'Morocco' },
  { value: 'GB', label: 'United Kingdom' },
  { value: 'US', label: 'United States' },
  { value: 'OT', label: 'Other' },
]

export const formatPoints = (n: number) => new Intl.NumberFormat('en-US').format(n)

export const formatDate = (iso: string | null | undefined) =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
