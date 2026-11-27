import axios from 'axios'
import { compressImage } from '@/lib/compress-image'
import type { ChatConversation, ChatMessage, ChatOrderOption, ChatPage, ChatSide } from './types'

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
    if (value === undefined || value === null || value === '' || key === 'files' || key === 'clientUuid') continue
    form.append(key, String(value))
  }
  if (input.clientUuid) form.append('client_uuid', input.clientUuid)

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

export function uuid(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID()
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}
