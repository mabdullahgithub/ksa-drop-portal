export interface ProductImage {
  id: number
  product_id: number
  src: string
  position: number
  alt_text: string | null
  created_at: string
  updated_at: string
}

export interface Product {
  id: number
  handle: string
  title: string
  body_html: string | null
  vendor: string | null
  product_category: string | null
  type: string | null
  tags: string[] | null
  published: boolean

  variant_sku: string | null
  variant_price: string | null
  variant_compare_at_price: string | null
  cost_per_item: string | null
  variant_inventory_qty: number
  variant_inventory_policy: string
  variant_fulfillment_service: string | null
  variant_requires_shipping: boolean
  variant_taxable: boolean
  variant_grams: string | null
  variant_barcode: string | null

  option1_name: string | null
  option1_value: string | null
  option2_name: string | null
  option2_value: string | null
  option3_name: string | null
  option3_value: string | null

  seo_title: string | null
  seo_description: string | null
  available_in: string[] | null
  uae_prices: Record<string, any> | null

  included_saudi_arabia: boolean
  price_saudi_arabia: string | null
  compare_at_price_saudi_arabia: string | null
  included_pakistan: boolean
  price_pakistan: string | null
  compare_at_price_pakistan: string | null

  status: 'active' | 'draft' | 'archived'
  primary_image: string | null

  images?: ProductImage[]
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface ProductFilters {
  search?: string
  status?: string
  vendor?: string
  type?: string
  published?: string
  min_price?: number
  max_price?: number
  low_stock?: number
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface ProductStatistics {
  total_products: number
  active_products: number
  draft_products: number
  archived_products: number
  out_of_stock: number
  low_stock: number
  total_inventory_value: number
}

export interface FilterOption {
  value: string
  label: string
}

export interface ProductFilterOptions {
  statuses: FilterOption[]
  vendors: FilterOption[]
  types: FilterOption[]
}

export interface PaginatedProducts {
  data: Product[]
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
