import type { PartnerStatus, PartnerTier, PartnerType, PayoutMethod } from '@/features/partners/types'

export type PartnerRow = {
  id: number
  code: string
  display_name: string
  company_name: string | null
  type: PartnerType
  status: PartnerStatus
  tier: PartnerTier
  points_balance: number
  phone: string
  country: string
  city: string | null
  user: { id: number; name: string; email: string } | null
  client_id: number | null
  referred_clients_count: number
  active_clients_count: number
  leads_count: number
  has_id_document: boolean
  created_at: string
  approved_at: string | null
}

export type PartnerDetail = PartnerRow & {
  whatsapp: string | null
  website: string | null
  social_links: Record<string, string>
  audience_size: string | null
  promotion_plan: string | null
  motivation: string | null
  id_document_type: string | null
  has_id_document_back?: boolean
  payout_method: PayoutMethod
  terms_version: string | null
  terms_accepted_at: string | null
  reviewed_by: string | null
  reviewed_at: string | null
  rejection_reason: string | null
  admin_notes: string | null
  own_client: { id: number; company_name: string } | null
  referral_url: string
  referred_clients: {
    id: number
    company_name: string
    client_id: string
    status: string
    referred_at: string | null
    activated_at: string | null
  }[]
}

export type AdminTransaction = {
  id: number
  type: string
  points: number
  description: string | null
  client: string | null
  by: string | null
  created_at: string
}

export type LeadRow = {
  id: number
  name: string
  company: string | null
  phone: string
  email: string | null
  store_url: string | null
  monthly_orders: string | null
  message: string | null
  notes: string | null
  status: 'new' | 'contacted' | 'converted' | 'lost'
  source: string
  partner: { id: number; code: string; display_name: string } | null
  client: { id: number; company_name: string } | null
  created_at: string
}

export type PayoutRow = {
  id: number
  points: number
  status: 'requested' | 'approved' | 'paid' | 'rejected'
  payout_method: PayoutMethod
  partner_note: string | null
  admin_note: string | null
  amount_paid: string | number | null
  currency: string | null
  paid_at: string | null
  has_proof: boolean
  handled_by: string | null
  partner: { id: number; code: string; display_name: string; points_balance: number } | null
  created_at: string
}

export type Stats = {
  pending: number
  approved: number
  referred_clients: number
  active_clients: number
  points_outstanding: number
  open_payouts: number
  new_leads: number
}

export type Settings = Record<string, number | string>
