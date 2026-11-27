import { useCallback, useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import { ArrowLeft, CheckCircle2, Eye, Loader2, Plus, RotateCcw } from 'lucide-react'
import { cn } from '@/lib/utils'
import { getEcho, useEchoConnection } from '@/lib/echo'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/empty-state'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import type { PageProps } from '@/types'
import { errorMessage, portalChatApi, portalChats } from '@/features/chats/api'
import { CATEGORY_LABELS } from '@/features/chats/constants'
import { StatusBadge } from '@/features/chats/components/shared/chat-bits'
import { Composer } from '@/features/chats/components/shared/composer'
import { ConversationList } from '@/features/chats/components/shared/conversation-list'
import { MessageThread } from '@/features/chats/components/shared/message-thread'
import { useChatInbox } from '@/features/chats/hooks/use-chat-inbox'
import { useChatThread } from '@/features/chats/hooks/use-chat-thread'
import type { ChatCategory, ChatClientDetail, ChatConversation } from '@/features/chats/types'
import { NewSupportChat, type SupportDraft } from './new-support-chat'

const FILTERS = [
  { value: 'active', label: 'Active' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
  { value: 'any', label: 'All' },
]

/** ?chat=ID opens a chat (from a notification or email); ?new=1&order_id=…&order_number=… starts one about an order. */
function fromUrl() {
  const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search)
  const chat = Number(params.get('chat'))
  const orderId = Number(params.get('order_id'))
  return {
    chat: Number.isFinite(chat) && chat > 0 ? chat : null,
    draft:
      params.get('new') === '1'
        ? ({
            category: (params.get('category') as ChatCategory) || (orderId ? 'order_issue' : 'general'),
            order: orderId ? { id: orderId, order_number: params.get('order_number') ?? String(orderId) } : null,
          } satisfies SupportDraft)
        : null,
  }
}

function rememberInUrl(id: number | null) {
  const url = new URL(window.location.href)
  ;['new', 'order_id', 'order_number', 'category'].forEach((key) => url.searchParams.delete(key))
  if (id) url.searchParams.set('chat', String(id))
  else url.searchParams.delete('chat')
  window.history.replaceState(window.history.state, '', url)
}

/**
 * The client's Support page: their conversations with the KSA Drop team, in
 * the same layout as the team's inbox. They open chats, reply, attach
 * files, and mark a chat resolved (or reopen it).
 */
export function PortalSupport() {
  const { auth, chat } = usePage<PageProps>().props
  const impersonating = !!auth.impersonating
  const me = useMemo(() => ({ id: auth.user.id, name: auth.user.name, avatar_url: null }), [auth.user.id, auth.user.name])
  // Viewing as the client: no live connection as them, polling only.
  const realtime = !!chat?.realtime && !impersonating
  const echo = getEcho(realtime)
  const live = useEchoConnection(realtime) === 'connected'
  const poll = chat?.poll ?? { thread_ms: 5000, inbox_ms: 20000 }

  const initial = useMemo(fromUrl, [])
  const [filter, setFilter] = useState('active')
  const [selectedId, setSelectedId] = useState<number | null>(initial.chat)
  const [detail, setDetail] = useState<ChatClientDetail | null>(null)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [mobileThread, setMobileThread] = useState(!!initial.chat)
  const [draft, setDraft] = useState<SupportDraft | null>(initial.draft)
  const [newOpen, setNewOpen] = useState(!!initial.draft && !impersonating)
  const [busy, setBusy] = useState(false)

  const load = useCallback((cursor?: string) => portalChats.list({ status: filter, cursor }), [filter])

  const inbox = useChatInbox({
    side: 'client',
    load,
    key: filter,
    echo,
    live,
    pollMs: poll.inbox_ms,
    userId: me.id,
    openId: selectedId,
  })

  const applyConversation = useCallback(
    (conversation: ChatConversation) => {
      setDetail((current) =>
        current && current.conversation.id === conversation.id ? { ...current, conversation: { ...current.conversation, ...conversation } } : current
      )
      inbox.upsert(conversation)
    },
    [inbox.upsert] // eslint-disable-line react-hooks/exhaustive-deps
  )

  const thread = useChatThread({
    api: portalChatApi,
    conversationId: selectedId,
    echo,
    live,
    pollMs: poll.thread_ms,
    me,
    initialReadUpTo: detail?.team_read_up_to ?? 0,
    onConversation: applyConversation,
  })

  const loadDetail = useCallback(async (id: number, quiet = false) => {
    if (!quiet) setLoadingDetail(true)
    try {
      const { data } = await axios.get<ChatClientDetail>(`/portal/api/chats/${id}`)
      setDetail(data)
    } catch (error) {
      if (!quiet) {
        toast.error(errorMessage(error, 'That chat is not available'))
        setSelectedId(null)
        rememberInUrl(null)
      }
    } finally {
      if (!quiet) setLoadingDetail(false)
    }
  }, [])

  useEffect(() => {
    setDetail(null)
    if (selectedId) void loadDetail(selectedId)
  }, [selectedId, loadDetail])

  // Polling: a reply from the team can change the status; refresh the header with it.
  const lastId = thread.messages[thread.messages.length - 1]?.id
  useEffect(() => {
    if (selectedId && !live && lastId && lastId > 0 && detail) void loadDetail(selectedId, true)
  }, [lastId]) // eslint-disable-line react-hooks/exhaustive-deps

  const open = (id: number) => {
    setSelectedId(id)
    setMobileThread(true)
    inbox.markRead(id)
    rememberInUrl(id)
  }

  const move = async (action: 'resolve' | 'reopen') => {
    if (!detail) return
    setBusy(true)
    try {
      const { data } = await axios.post(`/portal/api/chats/${detail.conversation.id}/${action}`)
      applyConversation(data.conversation)
      toast.success(action === 'resolve' ? 'Marked as resolved. Thanks!' : 'Chat reopened')
    } catch (error) {
      toast.error(errorMessage(error, 'That did not work'))
    } finally {
      setBusy(false)
    }
  }

  const c = detail?.conversation
  const active = c && ['open', 'pending', 'on_hold'].includes(c.status)

  const startNew = (preset: SupportDraft | null = null) => {
    setDraft(preset)
    setNewOpen(true)
  }

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main fixed fluid className='flex flex-col gap-3'>
        <div className='flex flex-wrap items-center justify-between gap-2'>
          <div>
            <h1 className='text-lg font-semibold tracking-tight'>Support</h1>
            <p className='text-sm text-muted-foreground'>Chat with the KSA Drop team about an order, your stock, billing or anything else.</p>
          </div>
          {!impersonating && (
            <Button size='sm' onClick={() => startNew()}>
              <Plus className='size-4' /> New chat
            </Button>
          )}
        </div>

        {impersonating && (
          <div className='flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200'>
            <Eye className='size-4 shrink-0' />
            You are viewing this client&apos;s chats. Reply from the Live Chat inbox, so messages are sent as you, not as the client.
          </div>
        )}

        <section className='flex min-h-0 flex-1 overflow-hidden rounded-lg border bg-background'>
          {/* ── My conversations ───────────────────────────────── */}
          <div className={cn('min-h-0 w-full min-w-0 flex-col border-e md:flex md:w-72 md:shrink-0 lg:w-80', mobileThread ? 'hidden' : 'flex')}>
            <div className='border-b p-3'>
              <div className='grid grid-cols-4 gap-1 rounded-lg bg-muted p-1'>
                {FILTERS.map((f) => (
                  <button
                    key={f.value}
                    type='button'
                    onClick={() => setFilter(f.value)}
                    className={cn(
                      'rounded-md px-1.5 py-1 text-xs font-medium transition-colors',
                      filter === f.value ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'
                    )}
                  >
                    {f.label}
                  </button>
                ))}
              </div>
            </div>

            <ConversationList
              side='client'
              items={inbox.items}
              selectedId={selectedId}
              onSelect={(conversation) => open(conversation.id)}
              loading={inbox.loading}
              hasMore={!!inbox.nextCursor}
              loadingMore={inbox.loadingMore}
              onMore={inbox.more}
              empty={
                <div className='space-y-3 text-center'>
                  <p className='text-sm text-muted-foreground'>{filter === 'active' ? 'No open chats.' : 'Nothing here.'}</p>
                  {!impersonating && filter === 'active' && (
                    <Button size='sm' variant='outline' onClick={() => startNew()}>
                      <Plus className='size-4' /> Start a chat
                    </Button>
                  )}
                </div>
              }
            />
          </div>

          {/* ── Conversation ───────────────────────────────────── */}
          <div className={cn('min-w-0 flex-1 flex-col md:flex', mobileThread ? 'flex' : 'hidden')}>
            {!selectedId ? (
              <EmptyState
                bot='blob'
                size='lg'
                className='h-full p-8'
                title='How can we help?'
                description='Report a problem with an order, ask about your stock or billing, or anything else. We reply right here.'
                action={
                  !impersonating ? (
                    <div className='flex flex-wrap justify-center gap-2'>
                      {(['order_issue', 'shipment', 'inventory', 'billing'] as ChatCategory[]).map((key) => (
                        <Button key={key} size='sm' variant='outline' onClick={() => startNew({ category: key })}>
                          {CATEGORY_LABELS[key]}
                        </Button>
                      ))}
                    </div>
                  ) : undefined
                }
              />
            ) : loadingDetail || !c ? (
              <div className='flex flex-1 items-center justify-center'>
                <Loader2 className='size-5 animate-spin text-muted-foreground' />
              </div>
            ) : (
              <>
                <div className='flex items-center gap-2 border-b px-3 py-2.5'>
                  <Button size='icon' variant='ghost' className='size-8 shrink-0 md:hidden' onClick={() => setMobileThread(false)} aria-label='Back to my chats'>
                    <ArrowLeft className='size-4 rtl:rotate-180' />
                  </Button>
                  <div className='min-w-0 flex-1'>
                    <div className='flex items-center gap-2'>
                      <span className='truncate text-sm font-semibold'>{c.subject}</span>
                      <StatusBadge status={c.status} side='client' className='hidden sm:inline-flex' />
                    </div>
                    <div className='truncate text-xs text-muted-foreground'>
                      {c.reference} · {CATEGORY_LABELS[c.category]}
                      {c.order && ` · Order ${c.order.order_number}`}
                    </div>
                  </div>
                  {!detail.read_only && active && (
                    <Button size='sm' variant='outline' className='h-8 gap-1' onClick={() => move('resolve')} disabled={busy}>
                      <CheckCircle2 className='size-3.5' />
                      <span className='hidden sm:inline'>Mark as resolved</span>
                    </Button>
                  )}
                  {!detail.read_only && c.status === 'resolved' && (
                    <Button size='sm' variant='outline' className='h-8 gap-1' onClick={() => move('reopen')} disabled={busy}>
                      <RotateCcw className='size-3.5' />
                      <span className='hidden sm:inline'>Reopen</span>
                    </Button>
                  )}
                </div>

                {c.status === 'resolved' && (
                  <div className='flex items-center gap-2 border-b bg-emerald-50 px-4 py-2 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200'>
                    <CheckCircle2 className='size-4 shrink-0' />
                    This chat is resolved. Still need help? Just reply and it reopens.
                  </div>
                )}

                <div className='flex min-h-0 flex-1 bg-muted/20'>
                  <MessageThread
                    messages={thread.messages}
                    side='client'
                    meId={me.id}
                    loading={thread.loading}
                    hasMore={thread.hasMore}
                    loadingOlder={thread.loadingOlder}
                    onLoadOlder={thread.loadOlder}
                    readUpTo={thread.readUpTo}
                    typing={thread.typing}
                    onRetry={thread.retry}
                    onDiscard={thread.discard}
                  />
                </div>

                <Composer
                  autoFocusKey={c.id}
                  onSend={thread.send}
                  onTyping={thread.whisperTyping}
                  placeholder='Write a message…'
                  disabledReason={
                    detail.read_only ? (
                      'Viewing as the client: replies are sent from the Live Chat inbox.'
                    ) : c.status === 'closed' ? (
                      <div className='flex flex-wrap items-center gap-2'>
                        <span>This chat is closed.</span>
                        <Button
                          size='sm'
                          variant='outline'
                          className='h-7'
                          onClick={() => startNew({ category: c.category, order: c.order, subject: `Follow-up: ${c.subject}` })}
                        >
                          Start a new chat
                        </Button>
                      </div>
                    ) : undefined
                  }
                />
              </>
            )}
          </div>
        </section>

        <NewSupportChat
          open={newOpen}
          onOpenChange={(value) => {
            setNewOpen(value)
            if (!value) rememberInUrl(selectedId)
          }}
          draft={draft}
          onCreated={(conversation) => {
            if (filter !== 'active' && filter !== 'any') setFilter('active')
            inbox.upsert(conversation)
            open(conversation.id)
          }}
        />
      </Main>
    </>
  )
}
