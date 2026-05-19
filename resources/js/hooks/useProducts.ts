import { useState, useEffect, useCallback } from 'react'
import type {
  Product,
  ProductFilters,
  PaginatedProducts,
  ProductStatistics,
  ProductFilterOptions,
} from '@/types/product'

export function useProducts(initialFilters: ProductFilters = {}) {
  const [products, setProducts] = useState<PaginatedProducts | null>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState<ProductFilters>(initialFilters)

  const fetchProducts = useCallback(async (newFilters?: ProductFilters) => {
    setLoading(true)
    const params = new URLSearchParams()
    const activeFilters = newFilters || filters
    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })
    try {
      const response = await fetch(`/api/products?${params}`)
      const data = await response.json()
      setProducts(data)
    } catch (error) {
      console.error('Error fetching products:', error)
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => {
    fetchProducts()
  }, [])

  const updateFilters = useCallback((newFilters: Partial<ProductFilters>) => {
    setFilters((prev) => ({ ...prev, ...newFilters }))
    fetchProducts({ ...filters, ...newFilters })
  }, [filters, fetchProducts])

  const refresh = useCallback(() => {
    fetchProducts()
  }, [fetchProducts])

  const meta = products ? {
    current_page: products.current_page,
    last_page: products.last_page,
    per_page: products.per_page,
    total: products.total,
    from: products.from,
    to: products.to,
  } : undefined

  return { products: products?.data || [], meta, loading, filters, updateFilters, refresh }
}

export function useProduct(productId: number) {
  const [product, setProduct] = useState<Product | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!productId) return
    const fetchProduct = async () => {
      try {
        const res = await fetch(`/api/products/${productId}`)
        setProduct(await res.json())
      } catch (error) {
        console.error('Error fetching product:', error)
      } finally {
        setLoading(false)
      }
    }
    fetchProduct()
  }, [productId])

  return { product, loading }
}

export function useProductStatistics() {
  const [statistics, setStatistics] = useState<ProductStatistics | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchStatistics = async () => {
      try {
        const res = await fetch('/api/products/statistics')
        setStatistics(await res.json())
      } catch (error) {
        console.error('Error fetching product statistics:', error)
      } finally {
        setLoading(false)
      }
    }
    fetchStatistics()
  }, [])

  return { statistics, loading }
}

export function useProductFilterOptions() {
  const [options, setOptions] = useState<ProductFilterOptions | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchOptions = async () => {
      try {
        const res = await fetch('/api/products/filter-options')
        setOptions(await res.json())
      } catch (error) {
        console.error('Error fetching filter options:', error)
      } finally {
        setLoading(false)
      }
    }
    fetchOptions()
  }, [])

  return { options, loading }
}

export function useProductMutations() {
  const [loading, setLoading] = useState(false)

  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  const updateProduct = async (productId: number, data: Partial<Product>): Promise<boolean> => {
    setLoading(true)
    try {
      const res = await fetch(`/api/products/${productId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        body: JSON.stringify(data),
      })
      return res.ok
    } catch {
      return false
    } finally {
      setLoading(false)
    }
  }

  const exportProducts = (filters: ProductFilters = {}) => {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })
    window.location.href = `/api/products/export?${params}`
  }

  return { loading, updateProduct, exportProducts }
}
