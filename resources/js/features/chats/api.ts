import axios from 'axios'
import { compressImage } from '@/lib/compress-image'
import { clientInfo } from './client-info'
import type { ChatConversation, ChatFilterCounts, ChatMessage, ChatOrderOption, ChatPage, ChatSharedItem, ChatSide, VoiceClip } from './types'

/**
 * The endpoints the shared chat pieces call. The team and the portal have
 * the same shape under different roots, so the thread, the composer and the
 * list work unchanged on both screens.
 */
export interface ChatApi {
  side: ChatSide
  messages(id: number, params?: { before_id?: number; after_id?: number }): Promise<ChatPage<ChatMessage>>
  send(id: number, input: SendInput): Promise<ChatMessage>
  read(id: number, messageId: number): Promise<void>
}

export interface SendInput {
  body: string
  kind?: 'message' | 'note'
  clientUuid: string
  files: File[]
  voice?: VoiceClip
  items?: ChatSharedItem[]
}

function api(root: string, side: ChatSide): ChatApi {
  return {
    side,
    async messages(id, params) {
      const { data } = await axios.get(`${root}/${id}/messages`, { params })
      return data
    },
    async send(id, input) {
      const { data } = await axios.post(`${root}/${id}/messages`, await toForm(input))
      return data.message
    },
    async read(id, messageId) {
      await axios.post(`${root}/${id}/read`, { message_id: messageId })
    },
  }
}

export const teamChatApi = api('/api/chats', 'team')
export const portalChatApi = api('/portal/api/chats', 'client')

/** Text and files as multipart, photos shrunk first. */
export async function toForm(input: { files?: File[]; clientUuid?: string } & object): Promise<FormData> {
  const form = new FormData()

  for (const [key, value] of Object.entries(input as Record<string, unknown>)) {
    if (value === undefined || value === null || value === '' || ['files', 'clientUuid', 'voice', 'items'].includes(key)) continue
    form.append(key, String(value))
  }
  if (input.clientUuid) form.append('client_uuid', input.clientUuid)
  form.append('client_info', await clientInfo())

  const voice = (input as { voice?: VoiceClip }).voice
  if (voice) {
    form.append('voice', voice.file, voice.file.name)
    form.append('voice_duration', String(voice.seconds))
    if (voice.waveform) form.append('voice_waveform', JSON.stringify(voice.waveform))
  }

  // Only ids go up; the server looks each one up among the client's own.
  for (const item of (input as { items?: ChatSharedItem[] }).items ?? []) {
    form.append(item.type === 'order' ? 'orders[]' : 'products[]', String(item.id))
  }

  for (const file of input.files ?? []) {
    form.append('files[]', await shrink(file), file.name)
  }

  return form
}

async function shrink(file: File): Promise<Blob> {
  // GIFs keep their animation; small photos are left alone.
  if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size < 300 * 1024) return file
  try {
    const blob = await compressImage(file, 1600, 0.8)
    return blob.size < file.size ? new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }) : file
  } catch {
    return file
  }
}

/** The message text an axios error carries, or a fallback. */
export function errorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
    return first || data?.message || fallback
  }
  return fallback
}

export const teamChats = {
  async list(params: Record<string, string | undefined>): Promise<{
    data: ChatConversation[]
    next_cursor: string | null
    counts?: ChatFilterCounts
  }> {
    const { data } = await axios.get('/api/chats', { params })
    return data
  },
  async start(input: Record<string, unknown> & { files: File[]; clientUuid: string }): Promise<ChatConversation> {
    const { data } = await axios.post('/api/chats', await toForm(input))
    return data.conversation
  },
  async orders(clientId: number, q: string): Promise<ChatOrderOption[]> {
    const { data } = await axios.get('/api/chats/lookup/orders', { params: { client_id: clientId, q } })
    return data.data
  },
}

export const portalChats = {
  async list(params: Record<string, string | undefined>): Promise<{ data: ChatConversation[]; next_cursor: string | null }> {
    const { data } = await axios.get('/portal/api/chats', { params })
    return data
  },
  async start(input: Record<string, unknown> & { files: File[]; clientUuid: string }): Promise<ChatConversation> {
    const { data } = await axios.post('/portal/api/chats', await toForm(input))
    return data.conversation
  },
  async orders(q: string): Promise<ChatOrderOption[]> {
    const { data } = await axios.get('/portal/api/chats/orders', { params: { q } })
    return data.data
  },
}

/** The signed-in client's own orders and products, for the attach picker. */
export const portalItemSources = {
  async orders(q: string): Promise<ChatSharedItem[]> {
    const rows = await portalChats.orders(q)
    return rows.map((order) => ({
      type: 'order' as const,
      id: order.id,
      title: order.order_number,
      status: order.status,
      total: order.total,
      currency: order.currency,
      date: order.created_at,
    }))
  },
  async products(q: string): Promise<ChatSharedItem[]> {
    const { data } = await axios.get('/portal/api/chats/products', { params: { q } })
    return data.data
  },
}

/** The orders and inventory of one chat's client, for an agent to attach. */
export function teamItemSources(conversationId: number, allowed: { orders: boolean; products: boolean }) {
  const search = (kind: 'orders' | 'products') => async (q: string): Promise<ChatSharedItem[]> => {
    const { data } = await axios.get(`/api/chats/${conversationId}/share/${kind}`, { params: { q } })
    return data.data
  }

  return {
    orders: allowed.orders ? search('orders') : undefined,
    products: allowed.products ? search('products') : undefined,
  }
}

export function uuid(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID()
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}
