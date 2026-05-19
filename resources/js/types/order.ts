export interface Order {
  id: number
  order_number: string
  shopify_order_id: string | null
  customer_name: string | null
  customer_email: string | null
  customer_phone: string | null
  billing_name: string | null
  billing_street: string | null
  billing_address1: string | null
  billing_address2: string | null
  billing_city: string | null
  billing_zip: string | null
  billing_province: string | null
  billing_country: string | null
  billing_phone: string | null
  shipping_name: string | null
  shipping_street: string | null
  shipping_address1: string | null
  shipping_address2: string | null
  shipping_city: string | null
  shipping_zip: string | null
  shipping_province: string | null
  shipping_country: string | null
  shipping_phone: string | null
  financial_status: 'pending' | 'paid' | 'refunded' | 'partially_refunded'
  fulfillment_status: 'pending' | 'unfulfilled' | 'fulfilled' | 'cancelled'
  payment_method: string | null
  payment_reference: string | null
  currency: string
  subtotal: string
  shipping_cost: string
  taxes: string
  total: string
  discount_amount: string
  shipping_method: string | null
  outstanding_balance: string
  refunded_amount: string
  paid_at: string | null
  fulfilled_at: string | null
  cancelled_at: string | null
  notes: string | null
  note_attributes: Record<string, any> | null
  tags: string[] | null
  risk_level: string | null
  source: string | null
  vendor: string | null
  utm_source: string | null
  utm_medium: string | null
  utm_campaign: string | null
  utm_id: string | null
  ip_address: string | null
  accepts_marketing: boolean
  created_at: string
  updated_at: string
  deleted_at: string | null
  formatted_total: string
  status_color: 'success' | 'warning' | 'info' | 'error' | 'default'
  financial_status_color: 'success' | 'warning' | 'info' | 'error' | 'default'
  items?: OrderItem[]
}

export interface OrderItem {
  id: number
  order_id: number
  lineitem_name: string
  lineitem_quantity: number
  lineitem_price: string
  lineitem_compare_at_price: string | null
  lineitem_sku: string | null
  lineitem_requires_shipping: boolean
  lineitem_taxable: boolean
  lineitem_fulfillment_status: string | null
  lineitem_discount: string
  variant_name: string | null
  total_price: number
  formatted_price: string
  created_at: string
  updated_at: string
}

export interface OrderFilters {
  search?: string
  fulfillment_status?: string
  financial_status?: string
  payment_method?: string
  start_date?: string
  end_date?: string
  utm_source?: string
  utm_campaign?: string
  risk_level?: string
  country?: string
  min_total?: number
  max_total?: number
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface OrderStatistics {
  total_orders: number
  total_revenue: number
  average_order_value: number
  by_fulfillment_status: Array<{ fulfillment_status: string; count: number }>
  by_financial_status: Array<{ financial_status: string; count: number }>
  by_payment_method: Array<{ payment_method: string; count: number }>
  by_utm_source: Array<{ utm_source: string; count: number }>
  by_country: Array<{ shipping_country: string; count: number }>
  top_products: Array<{
    lineitem_name: string
    total_quantity: number
    total_revenue: number
  }>
}

export interface FilterOption {
  value: string
  label: string
}

export interface OrderFilterOptions {
  fulfillment_statuses: FilterOption[]
  financial_statuses: FilterOption[]
  payment_methods: FilterOption[]
  utm_sources: FilterOption[]
  countries: FilterOption[]
  risk_levels: FilterOption[]
}

export interface PaginatedOrders {
  data: Order[]
  current_page: number
  from: number
  last_page: number
  per_page: number
  to: number
  total: number
  first_page_url: string
  last_page_url: string
  next_page_url: string | null
  prev_page_url: string | null
  path: string
  links: Array<{ url: string | null; label: string; active: boolean }>
}

export interface BulkUpdatePayload {
  order_ids: number[]
  action: 'update_fulfillment' | 'update_financial' | 'add_tags' | 'cancel'
  fulfillment_status?: string
  financial_status?: string
  tags?: string[]
}
