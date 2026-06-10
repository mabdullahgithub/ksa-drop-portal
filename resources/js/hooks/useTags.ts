import { useState, useEffect, useCallback } from 'react'
import axios from 'axios'
import { type Tag } from '@/features/tags/data/schema'

let cache: Tag[] | null = null
const listeners: Array<() => void> = []

function notify() {
  listeners.forEach((fn) => fn())
}

export function useAvailableTags() {
  const [tags, setTags]       = useState<Tag[]>(cache ?? [])
  const [loading, setLoading] = useState(!cache)

  useEffect(() => {
    const update = () => setTags(cache ?? [])
    listeners.push(update)
    return () => {
      const i = listeners.indexOf(update)
      if (i !== -1) listeners.splice(i, 1)
    }
  }, [])

  useEffect(() => {
    if (cache) return
    axios.get('/api/tags').then((res) => {
      cache = res.data.tags ?? []
      setTags(cache!)
      notify()
    }).finally(() => setLoading(false))
  }, [])

  const invalidate = useCallback(() => {
    cache = null
    setLoading(true)
    axios.get('/api/tags').then((res) => {
      cache = res.data.tags ?? []
      setTags(cache!)
      notify()
    }).finally(() => setLoading(false))
  }, [])

  return { tags, loading, invalidate }
}
