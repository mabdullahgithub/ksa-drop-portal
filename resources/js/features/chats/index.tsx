import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import axios from 'axios'
import { usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import { Loader2, RotateCcw, Search as SearchIcon, SquarePen, Wifi, WifiOff } from 'lucide-react'
import { cn } from '@/lib/utils'
import { getEcho, useEchoConnection } from '@/lib/echo'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { EmptyState } from '@/components/empty-state'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { SearchBeam } from '@/components/search-beam'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePermissions } from '@/hooks/use-permissions'
import type { PageProps } from '@/types'
import { errorMessage, teamChatApi, teamChats, teamItemSources } from './api'
import { PRIORITY_META } from './constants'
import { ClientContextPanel } from './components/client-context-panel'
import { ConversationHeader } from './components/conversation-header'
import { InboxStats } from './components/inbox-stats'
import { NewChat } from './components/new-chat'
import { Composer } from './components/shared/composer'
import { ConversationList } from './components/shared/conversation-list'
import { MessageThread } from './components/shared/message-thread'
import { useChatInbox } from './hooks/use-chat-inbox'
import { useChatThread } from './hooks/use-chat-thread'
import { useOpenChatInList } from './hooks/use-open-chat-in-list'
import type { ChatConversation, ChatFilterCounts, ChatPriority, ChatStats, ChatTeamDetail } from './types'

const STATUS_FILTERS = [
  { value: 'active', label: 'Active' },
  { value: 'open', label: 'Open' },
  { value: 'pending', label: 'Waiting on client' },
  { value: 'on_hold', label: 'On hold' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
  { value: 'any', label: 'All statuses' },
]

/** How many chats an option would show. */
function FilterCount({ value }: { value: number | undefined }) {
  if (value === undefined) return null
  return <span className='ms-1 rounded bg-muted px-1 text-[10px] leading-4 text-muted-foreground tabular-nums'>{value}</span>
}

/** The chat a notification linked to (?chat=ID). */
function chatFromUrl(): number | null {
  if (typeof window === 'undefined') return null
  const id = Number(new URLSearchParams(window.location.search).get('chat'))
  return Number.isFinite(id) && id > 0 ? id : null
}

function rememberInUrl(id: number | null) {
  const url = new URL(window.location.href)
  if (id) url.searchParams.set('chat', String(id))
  else url.searchParams.delete('chat')
  window.history.replaceState(window.history.state, '', url)
}

/**
 * The team's live chat inbox: the template's chat layout (list | thread)
 * with a client panel on wide screens, statuses, internal notes and live
 * updates.
 */
export function Chats() {
  const { auth, chat } = usePage<PageProps>().props
  const { can } = usePermissions()
  const me = useMemo(() => ({ id: auth.user.id, name: auth.user.name, avatar_url: null }), [auth.user.id, auth.user.name])
  const realtime = !!chat?.realtime
  const echo = getEcho(realtime)
  const connection = useEchoConnection(realtime)
  const live = connection === 'connected'
  const poll = chat?.poll ?? { thread_ms: 5000, inbox_ms: 20000 }

  const [status, setStatus] = useState('active')
  const [priority, setPriority] = useState('any')
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [selectedId, setSelectedId] = useState<number | null>(chatFromUrl)
  const [detail, setDetail] = useState<ChatTeamDetail | null>(null)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [mobileThread, setMobileThread] = useState(!!selectedId)
  const [contextOpen, setContextOpen] = useState(false)
  const [newChat, setNewChat] = useState(false)
  const [stats, setStats] = useState<ChatStats | null>(null)

  useEffect(() => {
    const timer = setTimeout(() => setQ(search.trim()), 300)
    return () => clearTimeout(timer)
  }, [search])

  const load = useCallback(
    (cursor?: string) =>
      teamChats.list({ status, priority: priority === 'any' ? undefined : priority, q: q || undefined, cursor }),
    [status, priority, q]
  )

  const inbox = useChatInbox<ChatFilterCounts>({
    side: 'team',
    load,
    key: `${status}|${priority}|${q}`,
    echo,
    live,
    pollMs: poll.inbox_ms,
    userId: me.id,
    openId: selectedId,
  })

  const applyConversation = useCallback(
    (conversation: ChatConversation) => {
      setDetail((current) =>
        current && current.conversation.id === conversation.id
          ? { ...current, conversation: { ...current.conversation, ...conversation } }
          : current
      )
      inbox.upsert(conversation)
    },
    [inbox.upsert] // eslint-disable-line react-hooks/exhaustive-deps
  )

  const thread = useChatThread({
    api: teamChatApi,
    conversationId: selectedId,
    echo,
    live,
    pollMs: poll.thread_ms,
    me,
    initialReadUpTo: detail?.client_read_up_to ?? 0,
    onConversation: applyConversation,
  })

  const loadDetail = useCallback(async (id: number, quiet = false) => {
    if (!quiet) setLoadingDetail(true)
    try {
      const { data } = await axios.get<ChatTeamDetail>(`/api/chats/${id}`)
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

  // A status change can follow a message: keep the header right
  // when polling (live, conversation.updated does it).
  const lastMessageId = thread.messages[thread.messages.length - 1]?.id
  const firstLoad = useRef(true)
  useEffect(() => {
    if (firstLoad.current) {
      firstLoad.current = false
      return
    }
    // Only for what can change the header: a status line, or someone else's
    // message. Your own reply is not worth a second request.
    const newest = thread.messages[thread.messages.length - 1]
    if (selectedId && !live && lastMessageId && lastMessageId > 0 && newest?.sender?.id !== me.id) {
      void loadDetail(selectedId, true)
    }
  }, [lastMessageId]) // eslint-disable-line react-hooks/exhaustive-deps

  const loadStats = useCallback(() => {
    if (!can('view chat stats')) return
    axios.get<ChatStats>('/api/chats/stats').then(({ data }) => setStats(data)).catch(() => {})
  }, [can])

  useEffect(() => {
    loadStats()
    const timer = setInterval(() => document.visibilityState === 'visible' && loadStats(), 60_000)
    return () => clearInterval(timer)
  }, [loadStats])


  const outside = useOpenChatInList({
    items: inbox.items,
    loading: inbox.loading,
    loadedKey: inbox.loadedKey,
    selectedId,
    open: detail?.conversation,
    onFilteredOut: () => {
      setSelectedId(null)
      setDetail(null)
      setMobileThread(false)
      rememberInUrl(null)
    },
  })

  // The open chat was resolved, held or reopened: the tiles follow at once.
  const openStatus = detail?.conversation.status
  useEffect(() => {
    if (openStatus === 'resolved' || openStatus === 'on_hold' || openStatus === 'open') loadStats()
  }, [openStatus, loadStats])

  const open = (id: number) => {
    setSelectedId(id)
    setMobileThread(true)
    inbox.markRead(id)
    rememberInUrl(id)
  }

  const close = () => {
    setMobileThread(false)
  }

  const conversation = detail?.conversation
  // Resolved or closed: nobody writes in it (ChatConversation::isLocked).
  const locked = conversation?.status === 'resolved' || conversation?.status === 'closed'

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main fixed fluid className='flex flex-col gap-3'>
        <div className='flex flex-wrap items-center gap-2 @6xl/content:grid @6xl/content:grid-cols-[1fr_auto_1fr]'>
          <div className='flex items-center gap-2'>
            <h1 className='text-lg font-semibold tracking-tight'>Live Chat</h1>
            <Tooltip>
              <TooltipTrigger asChild>
                <span
                  className={cn(
                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                    live ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'
                  )}
                >
                  {live ? <Wifi className='size-3' /> : <WifiOff className='size-3' />}
                  {live ? 'Live' : connection === 'connecting' ? 'Connecting' : 'Refreshing'}
                </span>
              </TooltipTrigger>
              <TooltipContent>
                {live ? 'New messages appear instantly.' : 'No live connection: the inbox checks for new messages every few seconds.'}
              </TooltipContent>
            </Tooltip>
          </div>
          <div className='order-last flex w-full justify-center @6xl/content:order-none @6xl/content:w-auto'>
            {can('view chat stats') && <InboxStats stats={stats} />}
          </div>
          <div className='ms-auto flex items-center gap-2 @6xl/content:ms-0 @6xl/content:justify-self-end'>
            {can('start chats') && (
              <Button size='sm' onClick={() => setNewChat(true)}>
                <SquarePen className='size-4' /> New chat
              </Button>
            )}
          </div>
        </div>

        <section className='flex min-h-0 flex-1 overflow-hidden rounded-lg border bg-background'>
          {/* ── Inbox ───────────────────────────────────────────── */}
          <div className={cn('min-h-0 w-full min-w-0 flex-col border-e md:flex md:w-72 md:shrink-0 lg:w-80', mobileThread ? 'hidden' : 'flex')}>
            <div className='border-b p-2.5'>
              <SearchBeam>
                <label className='flex h-9 w-full items-center rounded-md border border-input ps-2.5 focus-within:ring-1 focus-within:ring-ring'>
                  <SearchIcon className='me-2 size-4 shrink-0 text-muted-foreground' />
                  <span className='sr-only'>Search chats</span>
                  <input
                    type='text'
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder='Client, subject, CH- or order no.'
                    className='w-full flex-1 bg-transparent pe-2.5 text-sm outline-none placeholder:text-muted-foreground'
                  />
                </label>
              </SearchBeam>
              <div className='mt-2 grid grid-cols-2 gap-1.5'>
                <Select value={status} onValueChange={setStatus}>
                  <SelectTrigger size='sm' className='h-8 w-full min-w-0 text-xs' aria-label='Filter by status'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent align='start'>
                    {STATUS_FILTERS.map((f) => (
                      <SelectItem key={f.value} value={f.value}>
                        {f.label}
                        <FilterCount value={inbox.counts?.status[f.value]} />
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select value={priority} onValueChange={setPriority}>
                  <SelectTrigger size='sm' className='h-8 w-full min-w-0 text-xs' aria-label='Filter by priority'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent align='start'>
                    <SelectItem value='any'>
                      All priorities
                      <FilterCount value={inbox.counts?.priority.any} />
                    </SelectItem>
                    {(Object.keys(PRIORITY_META) as ChatPriority[]).map((key) => (
                      <SelectItem key={key} value={key}>
                        {PRIORITY_META[key].label}
                        <FilterCount value={inbox.counts?.priority[key]} />
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <ConversationList
              side='team'
              items={inbox.items}
              outside={outside}
              selectedId={selectedId}
              onSelect={(c) => open(c.id)}
              loading={inbox.loading}
              hasMore={!!inbox.nextCursor}
              loadingMore={inbox.loadingMore}
              onMore={inbox.more}
              empty={
                <p className='text-center text-sm text-muted-foreground'>
                  {q ? 'No chats match your search.' : priority !== 'any' ? 'No chats with this priority.' : 'No chats here.'}
                </p>
              }
            />
          </div>

          {/* ── Thread ──────────────────────────────────────────── */}
          <div className={cn('min-w-0 flex-1 flex-col md:flex', mobileThread ? 'flex' : 'hidden')}>
            {!selectedId ? (
              <EmptyState
                bot='blob'
                size='lg'
                className='h-full p-8'
                title='Pick a conversation'
                description='Chats clients start from their portal land here. Reply, add notes for the team, assign and resolve them.'
                action={
                  can('start chats') ? (
                    <Button size='sm' variant='outline' onClick={() => setNewChat(true)}>
                      <SquarePen className='size-4' /> Start a chat
                    </Button>
                  ) : undefined
                }
              />
            ) : loadingDetail || !detail ? (
              <div className='flex flex-1 items-center justify-center'>
                <Loader2 className='size-5 animate-spin text-muted-foreground' />
              </div>
            ) : (
              <>
                <ConversationHeader
                  detail={detail}
                  onBack={close}
                  onChanged={applyConversation}
                  contextOpen={contextOpen}
                  onToggleContext={() => setContextOpen((v) => !v)}
                />

                <div className='flex min-h-0 flex-1 bg-muted/20'>
                  <MessageThread
                    messages={thread.messages}
                    side='team'
                    meId={me.id}
                    reference={detail.conversation.reference}
                    clientId={detail.conversation.client?.id}
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
                  autoFocusKey={detail.conversation.id}
                  itemSources={teamItemSources(detail.conversation.id, {
                    orders: detail.permissions.share_orders,
                    products: detail.permissions.share_products,
                  })}
                  canReply={detail.permissions.reply}
                  canNote={detail.permissions.note}
                  onTyping={thread.whisperTyping}
                  onSend={thread.send}
                  placeholder={`Reply to ${detail.conversation.client?.company_name ?? 'the client'}…`}
                  disabledReason={
                    locked ? (
                      <div className='flex flex-wrap items-center gap-2'>
                        <span>
                          This chat is {detail.conversation.status} and locked.
                          {detail.permissions.status && ' Reopen it to reply.'}
                        </span>
                        {detail.permissions.status && (
                          <Button
                            size='sm'
                            variant='outline'
                            className='h-7 gap-1'
                            onClick={async () => {
                              try {
                                const { data } = await axios.patch(`/api/chats/${detail.conversation.id}/status`, { status: 'open' })
                                applyConversation(data.conversation)
                              } catch (error) {
                                toast.error(errorMessage(error, 'Could not reopen the chat'))
                              }
                            }}
                          >
                            <RotateCcw className='size-3.5' /> Reopen
                          </Button>
                        )}
                      </div>
                    ) : undefined
                  }
                />
              </>
            )}
          </div>

          {/* ── Client panel (xl and up) ────────────────────────── */}
          {detail && contextOpen && (
            <aside className='hidden w-80 shrink-0 border-s xl:block'>
              <ClientContextPanel detail={detail} messages={thread.messages} onOpen={open} />
            </aside>
          )}
        </section>

        <NewChat
          open={newChat}
          onOpenChange={setNewChat}
          onCreated={(conversation) => {
            inbox.upsert(conversation)
            open(conversation.id)
          }}
        />
      </Main>
    </>
  )
}
