import { useState, useEffect, useCallback } from 'react'

export function usePortalInventoryMutations() {
  const [loading, setLoading] = useState(false)

  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  const addProduct = async (
    payload: Record<string, unknown>,
    images?: File[]
  ): Promise<boolean | { errors: Record<string, string[]> }> => {
    setLoading(true)
    try {
      const form = new FormData()
      Object.entries(payload).forEach(([k, v]) => {
        if (v !== null && v !== undefined) form.append(k, String(v))
      })
      images?.forEach((f) => form.append('images[]', f))

      const response = await fetch('/portal/api/inventory', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        body: form,
      })
      if (!response.ok) {
        const error = await response.json()
        if (error.errors) return { errors: error.errors }
        throw new Error(error.message || 'Failed')
      }
      return true
    } catch {
      return false
    } finally {
      setLoading(false)
    }
  }

  const updateProduct = async (
    productId: number,
    payload: Record<string, unknown>,
    images?: File[],
    removeImageIds?: number[]
  ): Promise<boolean | { errors: Record<string, string[]> } | { message: string }> => {
    setLoading(true)
    try {
      const form = new FormData()
      Object.entries(payload).forEach(([k, v]) => {
        if (v !== null && v !== undefined) form.append(k, String(v))
      })
      images?.forEach((f) => form.append('images[]', f))
      removeImageIds?.forEach((id) => form.append('remove_image_ids[]', String(id)))
      form.append('_method', 'PUT')

      const response = await fetch(`/portal/api/inventory/${productId}`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        body: form,
      })
      if (!response.ok) {
        const error = await response.json()
        if (response.status === 422) return { message: error.message || 'This product cannot be edited.' }
        if (error.errors) return { errors: error.errors }
        throw new Error('Failed')
      }
      return true
    } catch {
      return false
    } finally {
      setLoading(false)
    }
  }

  const deleteProduct = async (
    productId: number
  ): Promise<boolean | { message: string }> => {
    setLoading(true)
    try {
      const response = await fetch(`/portal/api/inventory/${productId}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
      })
      if (!response.ok) {
        const error = await response.json()
        if (response.status === 422) return { message: error.message || 'This product cannot be deleted.' }
        throw new Error('Failed')
      }
      return true
    } catch {
      return false
    } finally {
      setLoading(false)
    }
  }

  return { loading, addProduct, updateProduct, deleteProduct }
}

export function usePortalDashboard() {
  const [data, setData] = useState<any>(null)
  const [loading, setLoading] = useState(true)

  const fetch_ = useCallback(async () => {
    setLoading(true)
    try {
      const response = await fetch('/portal/api/dashboard')
      const json = await response.json()
      setData(json)
    } catch (error) {
      console.error('Error fetching portal dashboard:', error)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { fetch_() }, [])

  return { data, loading, refresh: fetch_ }
}

export function usePortalOrders(initialFilters: Record<string, any> = {}) {
  const [orders, setOrders] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState(initialFilters)

  const fetchOrders = useCallback(async (newFilters?: Record<string, any>) => {
    setLoading(true)
    const params = new URLSearchParams()
    const activeFilters = newFilters || filters

    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })

    try {
      const response = await fetch(`/portal/api/orders?${params}`)
      const data = await response.json()
      setOrders(data)
    } catch (error) {
      console.error('Error fetching portal orders:', error)
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => { fetchOrders() }, [])

  const updateFilters = useCallback((newFilters: Record<string, any>) => {
    const merged = { ...filters, ...newFilters }
    setFilters(merged)
    fetchOrders(merged)
  }, [filters, fetchOrders])

  return {
    orders: orders?.data || [],
    meta: orders ? {
      current_page: orders.current_page,
      last_page: orders.last_page,
      per_page: orders.per_page,
      total: orders.total,
      from: orders.from,
      to: orders.to,
    } : null,
    loading,
    filters,
    updateFilters,
    refresh: () => fetchOrders(),
  }
}

export function usePortalInventory(initialFilters: Record<string, any> = {}) {
  const [products, setProducts] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState(initialFilters)

  const fetchProducts = useCallback(async (newFilters?: Record<string, any>) => {
    setLoading(true)
    const params = new URLSearchParams()
    const activeFilters = newFilters || filters

    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })

    try {
      const response = await fetch(`/portal/api/inventory?${params}`)
      const data = await response.json()
      setProducts(data)
    } catch (error) {
      console.error('Error fetching portal inventory:', error)
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => { fetchProducts() }, [])

  const updateFilters = useCallback((newFilters: Record<string, any>) => {
    const merged = { ...filters, ...newFilters }
    setFilters(merged)
    fetchProducts(merged)
  }, [filters, fetchProducts])

  return {
    products: products?.data || [],
    meta: products ? {
      current_page: products.current_page,
      last_page: products.last_page,
      per_page: products.per_page,
      total: products.total,
      from: products.from,
      to: products.to,
    } : null,
    loading,
    filters,
    updateFilters,
    refresh: () => fetchProducts(),
  }
}

export function usePortalProducts(initialFilters: Record<string, any> = {
  per_page: 20,
  sort_by: 'created_at',
  sort_order: 'desc',
}) {
  const [products, setProducts] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState(initialFilters)

  const fetchProducts = useCallback(async (newFilters?: Record<string, any>) => {
    setLoading(true)
    const params = new URLSearchParams()
    const activeFilters = newFilters || filters

    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })

    try {
      const response = await fetch(`/portal/api/products?${params}`)
      const data = await response.json()
      setProducts(data)
    } catch (error) {
      console.error('Error fetching portal products:', error)
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => { fetchProducts() }, [])

  const updateFilters = useCallback((newFilters: Record<string, any>) => {
    const merged = { ...filters, ...newFilters }
    setFilters(merged)
    fetchProducts(merged)
  }, [filters, fetchProducts])

  return {
    products: products?.data || [],
    meta: products ? {
      current_page: products.current_page,
      last_page: products.last_page,
      per_page: products.per_page,
      total: products.total,
      from: products.from,
      to: products.to,
    } : null,
    loading,
    filters,
    updateFilters,
    refresh: () => fetchProducts(),
  }
}

export function usePortalProduct(productId: number | null) {
  const [product, setProduct] = useState<any>(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!productId) { setProduct(null); return }
    setLoading(true)
    fetch(`/portal/api/products/${productId}`)
      .then((r) => r.json())
      .then(setProduct)
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [productId])

  return { product, loading }
}

export function usePortalProductFilterOptions() {
  const [options, setOptions] = useState<{ vendors: { value: string; label: string }[]; types: { value: string; label: string }[] } | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetch('/portal/api/products/filter-options')
      .then((r) => r.json())
      .then(setOptions)
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  return { options, loading }
}

export function usePortalRevenue(period: string = '6months') {
  const [data, setData] = useState<any>(null)
  const [loading, setLoading] = useState(true)

  const fetchRevenue = useCallback(async (p?: string) => {
    setLoading(true)
    try {
      const response = await fetch(`/portal/api/revenue?period=${p || period}`)
      const json = await response.json()
      setData(json)
    } catch (error) {
      console.error('Error fetching portal revenue:', error)
    } finally {
      setLoading(false)
    }
  }, [period])

  useEffect(() => { fetchRevenue() }, [])

  return { data, loading, refresh: fetchRevenue }
}

export function usePortalFinance(initialFilters: Record<string, any> = {}) {
  const [data, setData] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState<Record<string, any>>({ period: '6months', ...initialFilters })

  const fetchFinance = useCallback(async (newFilters?: Record<string, any>) => {
    setLoading(true)
    const params = new URLSearchParams()
    const activeFilters = newFilters || filters

    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })

    try {
      const response = await fetch(`/portal/api/finance?${params}`)
      const json = await response.json()
      setData(json)
    } catch (error) {
      console.error('Error fetching portal finance:', error)
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => { fetchFinance() }, [])

  const updateFilters = useCallback((newFilters: Record<string, any>) => {
    const merged = { ...filters, ...newFilters }
    setFilters(merged)
    fetchFinance(merged)
  }, [filters, fetchFinance])

  return {
    data,
    loading,
    filters,
    updateFilters,
    refresh: () => fetchFinance(),
  }
}
