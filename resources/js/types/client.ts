export interface ClientCharges {
  delivery?: number | null
  return?: number | null
  cod?: number | null
  warehousing?: number | null
  call_confirmation?: number | null
  vat?: number | null
  other?: number | null
}

export interface Client {
  id: number
  user_id: number
  created_by: number | null
  client_types: ('dropshipper' | 'fulfilment')[]
  company_name: string
  client_id: string
  contact_person: string | null
  phone: string | null
  secondary_phone: string | null
  address: string | null
  city: string | null
  country: string
  postal_code: string | null
  tax_id: string | null
  commercial_registration: string | null
  status: 'active' | 'inactive' | 'suspended'
  status_color: 'success' | 'warning' | 'error' | 'default'
  portal_features: ('orders' | 'inventory' | 'revenue' | 'finance' | 'products')[]
  charges: ClientCharges | null
  notes: string | null
  type_label: string
  is_dropshipper: boolean
  is_fulfilment: boolean
  user?: { id: number; name: string; email: string }
  creator?: { id: number; name: string }
  orders_count?: number
  total_revenue?: number
  products_count?: number
  verified_products_count?: number
  created_at: string
  updated_at: string
}

export interface ClientProduct {
  id: number
  client_id: number
  product_code: string
  name: string
  sku: string | null
  description: string | null
  quantity: number
  unit_price: string | null
  verification_status: 'pending' | 'verified'
  verified_at: string | null
  verified_by: number | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface ClientFilters {
  search?: string
  status?: string
  type?: string
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface ClientStatistics {
  total_clients: number
  active_clients: number
  inactive_clients: number
  suspended_clients: number
  dropshippers_count: number
  fulfilment_count: number
}

export interface FilterOption {
  value: string
  label: string
}

export interface ClientFilterOptions {
  statuses: FilterOption[]
  types: FilterOption[]
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  from: number
  last_page: number
  per_page: number
  to: number
  total: number
}

export type PaginatedClients = PaginatedResponse<Client>
export type PaginatedClientProducts = PaginatedResponse<ClientProduct>

export interface CreateClientPayload {
  name: string
  email: string
  client_types: ('dropshipper' | 'fulfilment')[]
  company_name: string
  client_id?: string
  contact_person?: string
  phone?: string
  secondary_phone?: string
  address?: string
  city?: string
  country?: string
  postal_code?: string
  tax_id?: string
  commercial_registration?: string
  portal_features?: ('orders' | 'inventory' | 'revenue' | 'finance' | 'products')[]
  charges?: ClientCharges
  notes?: string
}

export interface BulkClientUpdatePayload {
  client_ids: number[]
  action: 'activate' | 'deactivate' | 'suspend'
}
