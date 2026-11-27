import { useCallback, useEffect, useRef, useState } from 'react'
import type Echo from 'laravel-echo'
import { errorMessage, uuid, type ChatApi } from '../api'
import type { ChatConversation, ChatMessage, ChatPerson, ChatSharedItem, VoiceClip } from '../types'
import { toast } from 'sonner'

interface Options {
  api: ChatApi
  conversationId: number | null
  echo: Echo<'reverb'> | null
  /** The socket is up: no polling. */
  live: boolean
  pollMs: number
  me: ChatPerson & { id: number }
  /** The other side's read pointer, as the detail call reported it. */
  initialReadUpTo: number
  onConversation?: (conversation: ChatConversation) => void
}

interface Typing {
  name: string
  until: number
}

/**
 * One conversation's timeline: the latest page, older pages on demand, live
 * messages (or polling for them), the other side's "Seen" mark and typing,
 * marking read, and sending with an optimistic bubble that can be retried.
 */
export function useChatThread({ api, conversationId, echo, live, pollMs, me, initialReadUpTo, onConversation }: Options) {
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [loading, setLoading] = useState(false)
  const [hasMore, setHasMore] = useState(false)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [readUpTo, setReadUpTo] = useState(initialReadUpTo)
  const [typing, setTyping] = useState<Typing | null>(null)

  const idRef = useRef(conversationId)
  idRef.current = conversationId
  const markedRef = useRef(0)
  const lastWhisperRef = useRef(0)
  const onConversationRef = useRef(onConversation)
  onConversationRef.current = onConversation

  useEffect(() => setReadUpTo(initialReadUpTo), [initialReadUpTo])

  /** Add what came in, once each, in order; a confirmed send replaces its bubble. */
  const merge = useCallback((incoming: ChatMessage[]) => {
    if (incoming.length === 0) return
    setMessages((current) => {
      const next = [...current]
      for (const message of incoming) {
        if (message.conversation_id !== idRef.current) continue
        const byId = next.findIndex((m) => m.id === message.id)
        if (byId !== -1) {
          next[byId] = message
          continue
        }
        const byUuid = message.client_uuid ? next.findIndex((m) => m.pending && m.client_uuid === message.client_uuid) : -1
        if (byUuid !== -1) next[byUuid] = message
        else next.push(message)
      }
      const confirmed = next.filter((m) => !m.pending).sort((a, b) => a.id - b.id)
      const pending = next.filter((m) => m.pending)
      return [...confirmed, ...pending]
    })
  }, [])

  const lastConfirmedId = useCallback(
    () => messages.reduce((max, m) => (!m.pending && m.id > max ? m.id : max), 0),
    [messages]
  )
  const lastIdRef = useRef(0)
  lastIdRef.current = lastConfirmedId()

  // First page.
  useEffect(() => {
    setMessages([])
    setHasMore(false)
    setTyping(null)
    markedRef.current = 0
    if (!conversationId) return

    let cancelled = false
    setLoading(true)
    api
      .messages(conversationId)
      .then((page) => {
        if (cancelled) return
        setMessages(page.data)
        setHasMore(!!page.has_more)
      })
      .catch((error) => !cancelled && toast.error(errorMessage(error, 'Could not load the conversation')))
      .finally(() => !cancelled && setLoading(false))

    return () => {
      cancelled = true
    }
  }, [api, conversationId])

  const loadOlder = useCallback(async () => {
    const first = messages.find((m) => !m.pending)
    if (!conversationId || !first || loadingOlder) return
    setLoadingOlder(true)
    try {
      const page = await api.messages(conversationId, { before_id: first.id })
      setMessages((current) => [...page.data.filter((m) => !current.some((c) => c.id === m.id)), ...current])
      setHasMore(!!page.has_more)
    } catch (error) {
      toast.error(errorMessage(error, 'Could not load older messages'))
    } finally {
      setLoadingOlder(false)
    }
  }, [api, conversationId, messages, loadingOlder])

  /** Everything after the newest message we hold: the polling step, and the catch-up after a reconnect. */
  const catchUp = useCallback(async () => {
    const id = idRef.current
    if (!id || lastIdRef.current === 0) return
    try {
      const page = await api.messages(id, { after_id: lastIdRef.current })
      merge(page.data)
    } catch {
      // The next tick tries again.
    }
  }, [api, merge])

  // Poll while the socket is down; catch up the moment it is back.
  const wasLive = useRef(live)
  useEffect(() => {
    if (!conversationId) return
    if (live) {
      if (!wasLive.current) void catchUp()
      wasLive.current = true
      return
    }
    wasLive.current = false
    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') void catchUp()
    }, pollMs)
    return () => clearInterval(timer)
  }, [conversationId, live, pollMs, catchUp])

  // Live: messages and reads on this side's channel, typing in the shared room.
  useEffect(() => {
    if (!echo || !conversationId) return

    const own = api.side === 'team' ? echo.private(`chat.${conversationId}.team`) : echo.join(`chat.${conversationId}`)
    const room = api.side === 'team' ? echo.join(`chat.${conversationId}`) : own

    own.listen('.message.created', (event: { message: ChatMessage }) => merge([event.message]))
    own.listen('.messages.read', (event: { up_to: number; by: 'client' | 'team' }) => {
      if (event.by !== api.side) setReadUpTo((current) => Math.max(current, event.up_to))
    })
    own.listen('.conversation.updated', (event: { conversation: ChatConversation }) =>
      onConversationRef.current?.(event.conversation)
    )
    room.listenForWhisper('typing', (event: { name: string; side: string }) => {
      if (event.side !== api.side) setTyping({ name: event.name, until: Date.now() + 4000 })
    })

    return () => {
      echo.leave(`chat.${conversationId}`)
      if (api.side === 'team') echo.leave(`chat.${conversationId}.team`)
    }
  }, [echo, conversationId, api.side, merge])

  // A typing note fades on its own.
  useEffect(() => {
    if (!typing) return
    const timer = setTimeout(() => setTyping(null), Math.max(0, typing.until - Date.now()))
    return () => clearTimeout(timer)
  }, [typing])

  // A message from the other side ends their typing note.
  useEffect(() => {
    const last = messages[messages.length - 1]
    if (last && last.sender_type !== (api.side === 'team' ? 'staff' : 'client')) setTyping(null)
  }, [messages, api.side])

  // Mark read while the tab is in front.
  useEffect(() => {
    if (!conversationId) return
    const mark = () => {
      const last = lastIdRef.current
      if (last > markedRef.current && document.visibilityState === 'visible') {
        markedRef.current = last
        api.read(conversationId, last).catch(() => {
          markedRef.current = 0
        })
      }
    }
    mark()
    document.addEventListener('visibilitychange', mark)
    return () => document.removeEventListener('visibilitychange', mark)
  }, [api, conversationId, messages])

  const whisperTyping = useCallback(() => {
    if (!echo || !conversationId || Date.now() - lastWhisperRef.current < 2500) return
    lastWhisperRef.current = Date.now()
    try {
      echo.join(`chat.${conversationId}`).whisper('typing', { name: me.name, side: api.side })
    } catch {
      // Typing notes are best effort.
    }
  }, [echo, conversationId, me.name, api.side])

  const deliver = useCallback(
    async (draft: ChatMessage, files: File[], voice?: VoiceClip) => {
      const id = draft.conversation_id
      try {
        const message = await api.send(id, {
          body: draft.body ?? '',
          kind: draft.kind === 'note' ? 'note' : 'message',
          clientUuid: draft.client_uuid!,
          files,
          voice,
          // The bubble holds what was attached, so a retry sends it again.
          items: draft.items,
        })
        merge([message])
        return true
      } catch (error) {
        setMessages((current) =>
          current.map((m) =>
            m.client_uuid === draft.client_uuid && m.pending ? { ...m, pending: 'failed', retryFiles: files, retryVoice: voice } : m
          )
        )
        toast.error(errorMessage(error, 'Your message was not sent'))
        return false
      }
    },
    [api, merge]
  )

  const send = useCallback(
    (input: { body: string; kind: 'message' | 'note'; files: File[]; voice?: VoiceClip; items?: ChatSharedItem[] }) => {
      if (!conversationId) return Promise.resolve(false)
      const draft: ChatMessage = {
        id: -Date.now(),
        conversation_id: conversationId,
        kind: input.kind,
        internal: input.kind === 'note',
        sender_type: api.side === 'team' ? 'staff' : 'client',
        sender: me,
        body: input.body || null,
        meta: null,
        client_uuid: uuid(),
        items: input.items ?? [],
        attachments: [
          ...input.files.map((file, index) => ({
            id: -index - 1,
            name: file.name,
            mime: file.type,
            size: file.size,
            is_image: file.type.startsWith('image/'),
            is_voice: false,
            duration: null,
            width: null,
            height: null,
            url: file.type.startsWith('image/') ? URL.createObjectURL(file) : '',
          })),
          ...(input.voice
            ? [
                {
                  id: -input.files.length - 1,
                  name: input.voice.file.name,
                  mime: input.voice.file.type,
                  size: input.voice.file.size,
                  is_image: false,
                  is_voice: true,
                  duration: input.voice.seconds,
                  width: null,
                  height: null,
                  url: input.voice.url,
                },
              ]
            : []),
        ],
        created_at: new Date().toISOString(),
        edited_at: null,
        pending: 'sending',
      }
      setMessages((current) => [...current, draft])
      return deliver(draft, input.files, input.voice)
    },
    [api.side, conversationId, deliver, me]
  )

  const retry = useCallback(
    (clientUuid: string) => {
      const draft = messages.find((m) => m.client_uuid === clientUuid && m.pending === 'failed')
      if (!draft) return
      setMessages((current) => current.map((m) => (m === draft ? { ...m, pending: 'sending' } : m)))
      void deliver(draft, draft.retryFiles ?? [], draft.retryVoice)
    },
    [messages, deliver]
  )

  const discard = useCallback((clientUuid: string) => {
    setMessages((current) => current.filter((m) => !(m.pending && m.client_uuid === clientUuid)))
  }, [])

  return {
    messages,
    loading,
    hasMore,
    loadingOlder,
    loadOlder,
    readUpTo,
    typing: typing?.name ?? null,
    whisperTyping,
    send,
    retry,
    discard,
    merge,
  }
}
