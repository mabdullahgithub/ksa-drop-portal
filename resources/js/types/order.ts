export type CallStatus =
  | 'not_called'
  | 'no_answer'
  | 'confirmed'
  | 'cancelled'
  | 'wrong_number'

export type WhatsAppStatus =
  | 'sent'
  | 'followup_sent'
  | 'replied'
  | 'confirmed'
  | 'graveyard'
  | 'failed'

export interface WhatsAppMessage {
  id: number
  order_id: number
  direction: 'outbound' | 'inbound'
  provider_message_id: string | null
  template_key: string | null
  body: string | null
  status: string | null
  sent_at: string | null
  delivered_at: string | null
  read_at: string | null
  failed_at: string | null
  error_code: string | null
  error_message: string | null
  created_at: string
}

export interface Order {
  id: number
  client_id: number | null
  client?: {
    id: number
    company_name: string
    client_id: string
    type_label: string
  } | null
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
  // Outcome of the ops confirmation call — a separate axis from
  // fulfillment_status. 'no_answer' is what starts the WhatsApp flow.
  call_status: CallStatus
  call_attempts: number
  last_called_at: string | null
  call_notes: string | null
  whatsapp_status: WhatsAppStatus | null
  whatsapp_phone_e164: string | null
  whatsapp_sent_at: string | null
  whatsapp_followup_sent_at: string | null
  whatsapp_replied_at: string | null
  whatsapp_delivered_at: string | null
  whatsapp_read_at: string | null
  whatsapp_reply_message: string | null
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
  latest_shipment?: Shipment | null
  invoices?: Invoice[]
}

export interface TrackingEvent {
  status: string
  description: string
  location: string | null
  timestamp: string
  raw_status: string | null
}

export interface Shipment {
  id: number
  order_id: number
  courier: string
  tracking_number: string | null
  txlogistic_id: string
  sorting_code: string | null
  status: string
  status_label: string
  status_color: string
  courier_status: string | null
  courier_status_description: string | null
  tracking_history: TrackingEvent[] | null
  label_url: string | null
  weight: string
  service_type: string
  shipped_at: string | null
  delivered_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  error_message: string | null
  exception_note: string | null
  exception_escalated_at: string | null
  otp_verified: boolean
  otp_verified_at: string | null
  return_tracking_number: string | null
  created_at: string
}

export interface Invoice {
  id: number
  order_id: number
  shipment_id: number | null
  invoice_number: string
  type: 'shipping' | 'billing'
  status: 'draft' | 'issued'
  subtotal: string
  tax_amount: string
  shipping_amount: string
  total: string
  issued_at: string | null
  file_path: string | null
  created_at: string
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
  client_product_id: number | null
  product_id: number | null
  client_product: { id: number; name: string; sku: string | null } | null
  product: { id: number; title: string; variant_sku: string | null } | null
  total_price: number
  formatted_price: string
  created_at: string
  updated_at: string
}

export interface OrderFilters {
  search?: string
  fulfillment_status?: string[]
  financial_status?: string[]
  payment_method?: string[]
  start_date?: string
  end_date?: string
  utm_source?: string[]
  utm_campaign?: string
  risk_level?: string
  country?: string
  tags?: string[]
  min_total?: number
  max_total?: number
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  per_page?: number
  page?: number
  client_ids?: number[]
  client_type?: 'fulfilment' | 'dropshipper' | ''
  has_shipment?: boolean
  shipment_status?: string[]
  order_ids?: number[]
}

export interface ClientFilterOption {
  value: number
  label: string
  client_id: string
}

export interface OrderStatistics {
  total_orders: number
  total_revenue: number
  average_order_value: number
  by_fulfillment_status: Array<{ fulfillment_status: string; count: number }>
  by_financial_status: Array<{ financial_status: string; count: number }>
  by_shipment_status: Array<{ status: string; count: number }>
  by_payment_method: Array<{ payment_method: string; count: number }>
  by_utm_source: Array<{ utm_source: string; count: number }>
  by_country: Array<{ shipping_country: string; count: number }>
  by_tag: Array<{ id: number; name: string; color: string; count: number }>
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
  shipment_statuses: FilterOption[]
  payment_methods: FilterOption[]
  utm_sources: FilterOption[]
  countries: FilterOption[]
  risk_levels: FilterOption[]
  tags: FilterOption[]
  clients: ClientFilterOption[]
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
  action: 'update_fulfillment' | 'update_financial' | 'update_call_status' | 'add_tags' | 'cancel'
  fulfillment_status?: string
  financial_status?: string
  call_status?: string
  tags?: string[]
}
