import { useState, useEffect, useCallback } from 'react'
import type {
  Client,
  ClientFilters,
  ClientStatistics,
  ClientFilterOptions,
  PaginatedClients,
} from '@/types/client'

export function useClients(initialFilters: ClientFilters = {}) {
  const [clients, setClients] = useState<PaginatedClients | null>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState<ClientFilters>(initialFilters)

  const fetchClients = useCallback(async (newFilters?: ClientFilters) => {
    setLoading(true)
    const params = new URLSearchParams()
    const activeFilters = newFilters || filters

    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })

    try {
      const response = await fetch(`/api/clients?${params}`)
      const data = await response.json()
      setClients(data)
    } catch (error) {
      console.error('Error fetching clients:', error)
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => {
    fetchClients()
  }, [])

  const updateFilters = useCallback((newFilters: Partial<ClientFilters>) => {
    const merged = { ...filters, ...newFilters }
    setFilters(merged)
    fetchClients(merged)
  }, [filters, fetchClients])

  return {
    clients: clients?.data || [],
    meta: clients ? {
      current_page: clients.current_page,
      last_page: clients.last_page,
      per_page: clients.per_page,
      total: clients.total,
      from: clients.from,
      to: clients.to,
    } : null,
    loading,
    filters,
    updateFilters,
    refresh: () => fetchClients(),
  }
}

export function useClientStatistics() {
  const [stats, setStats] = useState<ClientStatistics | null>(null)
  const [loading, setLoading] = useState(true)

  const fetchStats = useCallback(async () => {
    setLoading(true)
    try {
      const response = await fetch('/api/clients/statistics')
      const data = await response.json()
      setStats(data)
    } catch (error) {
      console.error('Error fetching client statistics:', error)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchStats()
  }, [])

  return { stats, loading, refresh: fetchStats }
}

export function useClientFilterOptions() {
  const [options, setOptions] = useState<ClientFilterOptions | null>(null)

  useEffect(() => {
    fetch('/api/clients/filter-options')
      .then((res) => res.json())
      .then((data) => setOptions(data))
      .catch((error) => console.error('Error fetching filter options:', error))
  }, [])

  return options
}

export function useClientMutations() {
  const [loading, setLoading] = useState(false)

  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  const createClient = async (payload: Record<string, unknown>): Promise<boolean | { errors: Record<string, string[]> }> => {
    setLoading(true)
    try {
      const response = await fetch('/api/clients', {
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
        if (error.errors) {
          return { errors: error.errors }
        }
        throw error
      }
      return true
    } catch (error) {
      console.error('Error creating client:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const updateClient = async (clientId: number, payload: Record<string, unknown>): Promise<boolean | { errors: Record<string, string[]> }> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}`, {
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
        if (error.errors) {
          return { errors: error.errors }
        }
        throw error
      }
      return true
    } catch (error) {
      console.error('Error updating client:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const updateStatus = async (clientId: number, status: string): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}/status`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ status }),
      })
      if (!response.ok) throw new Error('Failed')
      return true
    } catch (error) {
      console.error('Error updating client status:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const resetPassword = async (
    clientId: number,
    password: string,
    passwordConfirmation: string
  ): Promise<boolean | { errors: Record<string, string[]> }> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}/reset-password`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ password, password_confirmation: passwordConfirmation }),
      })
      if (!response.ok) {
        const error = await response.json()
        if (error.errors) {
          return { errors: error.errors }
        }
        throw error
      }
      return true
    } catch (error) {
      console.error('Error resetting client password:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const sendResetLink = async (clientId: number): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}/send-reset-link`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
      })
      if (!response.ok) throw new Error('Failed')
      return true
    } catch (error) {
      console.error('Error sending reset link:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const deleteClient = async (clientId: number): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/clients/${clientId}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
        },
      })
      if (!response.ok) throw new Error('Failed')
      return true
    } catch (error) {
      console.error('Error deleting client:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const bulkUpdate = async (clientIds: number[], action: string): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch('/api/clients/bulk-update', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ client_ids: clientIds, action }),
      })
      if (!response.ok) throw new Error('Failed')
      return true
    } catch (error) {
      console.error('Error bulk updating clients:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const exportClients = async (filters: ClientFilters = {}): Promise<void> => {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    })

    window.open(`/api/clients/export?${params}`, '_blank')
  }

  return {
    loading,
    createClient,
    updateClient,
    updateStatus,
    resetPassword,
    sendResetLink,
    deleteClient,
    bulkUpdate,
    exportClients,
  }
}
