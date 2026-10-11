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

export interface ClientProductImage {
  id: number
  client_product_id: number
  path: string
  url: string
  position: number
  alt_text: string | null
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
  verification_status: 'pending' | 'verified' | 'rejected'
  verified_at: string | null
  verified_by: number | null
  notes: string | null
  rejection_reason: string | null
  is_out_of_stock: boolean
  images: ClientProductImage[]
  /** Changes the client asked for that the team has not decided yet. */
  pending_requests_count?: number
  created_at: string
  updated_at: string
}

export type InventoryRequestType = 'stock' | 'details'
export type InventoryRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

/** The product fields a details request can change. */
export type InventoryRequestField = 'name' | 'sku' | 'description' | 'unit_price' | 'notes'
export type InventoryRequestFields = Partial<Record<InventoryRequestField, string | null>>

export interface InventoryRequestImage {
  id: number
  url: string
}

/**
 * A change a client asked for on a verified product, as the Requests page and
 * the client's My Inventory page receive it.
 */
export interface InventoryRequest {
  id: number
  type: InventoryRequestType
  status: InventoryRequestStatus
  /** Goes up each time the client replaces the request while it waits. */
  revision: number
  created_at: string | null
  updated_at: string | null
  reviewed_at: string | null
  rejection_reason: string | null
  requested_by: string | null
  client: { id: number; company_name: string } | null
  product: {
    id: number
    product_code: string
    name: string
    sku: string | null
    quantity: number
    is_out_of_stock: boolean
    deleted: boolean
    images: InventoryRequestImage[]
  } | null
  // Stock.
  requested_quantity: number | null
  stock_at_request: number | null
  /** What the client asked stock to change by; below zero is a removal. */
  change: number | null
  received_quantity: number | null
  stock_before: number | null
  stock_after: number | null
  // Details: what was asked, what the fields held then, what they hold now.
  changes: InventoryRequestFields
  original: InventoryRequestFields
  current: InventoryRequestFields
  remove_image_ids: number[]
  images: InventoryRequestImage[]
  // Team only.
  reviewed_by?: string | null
  sku_in_use?: boolean
}

/** A request still waiting, as it comes attached to a product in My Inventory. */
export interface PendingInventoryRequest {
  id: number
  type: InventoryRequestType
  requested_quantity: number | null
  stock_at_request: number | null
  changes: (InventoryRequestFields & { remove_image_ids?: number[] }) | null
  images: InventoryRequestImage[]
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

/** One of a client's own products, as My Inventory lists it. */
export interface PortalInventoryProduct {
  id: number
  product_code: string
  name: string
  sku: string | null
  description: string | null
  quantity: number
  unit_price: string | null
  notes: string | null
  verification_status: 'pending' | 'verified' | 'rejected'
  rejection_reason: string | null
  is_out_of_stock: boolean
  images: ClientProductImage[]
  /** What the client asked to change that the team has not decided yet. */
  pending_requests?: PendingInventoryRequest[]
  created_at: string
}
