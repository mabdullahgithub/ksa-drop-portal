import { useState, useEffect, useCallback } from 'react'
import type { ClientProduct, PaginatedClientProducts } from '@/types/client'

export function useClientProducts(clientId: number | null) {
  const [products, setProducts] = useState<PaginatedClientProducts | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(false)

  const fetchProducts = useCallback(async () => {
    if (!clientId) return
    setLoading(true)
    setError(false)
    try {
      const response = await fetch(`/api/clients/${clientId}/products?per_page=100`)
      if (!response.ok) throw new Error('Failed to fetch')
      const data = await response.json()
      setProducts(data)
    } catch {
      setError(true)
    } finally {
      setLoading(false)
    }
  }, [clientId])

  useEffect(() => {
    fetchProducts()
  }, [fetchProducts])

  return {
    products: products?.data || [],
    loading,
    error,
    refresh: fetchProducts,
  }
}

export function useClientProductMutations() {
  const [loading, setLoading] = useState(false)

  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  const addProduct = async (
    clientId: number,
    payload: Record<string, unknown>
  ): Promise<boolean | { errors: Record<string, string[]> }> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}/products`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
      })
      if (!response.ok) {
        const error = await response.json()
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

  const updateProduct = async (
    clientId: number,
    productId: number,
    payload: Record<string, unknown>
  ): Promise<boolean | { errors: Record<string, string[]> }> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}/products/${productId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
      })
      if (!response.ok) {
        const error = await response.json()
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

  const verifyProduct = async (clientId: number, productId: number): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}/products/${productId}/verify`, {
        method: 'PATCH',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
      })
      if (!response.ok) throw new Error('Failed')
      return true
    } catch {
      return false
    } finally {
      setLoading(false)
    }
  }

  const deleteProduct = async (clientId: number, productId: number): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}/products/${productId}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
      })
      if (!response.ok) throw new Error('Failed')
      return true
    } catch {
      return false
    } finally {
      setLoading(false)
    }
  }

  return { loading, addProduct, updateProduct, verifyProduct, deleteProduct }
}
