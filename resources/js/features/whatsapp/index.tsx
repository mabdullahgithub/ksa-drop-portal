import { useState, useEffect, useCallback, useRef } from 'react'
import axios from 'axios'
import { format } from 'date-fns'
import {
  ArrowLeft,
  MessageCircle,
  Search as SearchIcon,
  RefreshCw,
  PanelRightOpen,
  PanelRightClose,
  Phone,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { WHATSAPP_STATUS_META } from '@/features/orders/data/call-status'
import { ConversationList } from './components/conversation-list'
import { MessageThread } from './components/message-thread'
import { OrderContextPanel } from './components/order-context-panel'
import {
  INBOX_FILTERS,
  type ConversationDetail,
  type ConversationSummary,
  type InboxStats,
} from './types'

export function WhatsAppInbox() {
  const [conversations, setConversations] = useState<ConversationSummary[]>([])
  const [stats, setStats] = useState<InboxStats | null>(null)
  const [filter, setFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [loadingList, setLoadingList] = useState(true)

  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [detail, setDetail] = useState<ConversationDetail | null>(null)
  const [loadingDetail, setLoadingDetail] = useState(false)

  // Mobile is single-pane: the list *is* the screen until a conversation is
  // picked, then the thread takes over. Desktop shows both at once.
  const [mobileShowThread, setMobileShowThread] = useState(false)
  const [showContext, setShowContext] = useState(true)

  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null)
  const [debouncedSearch, setDebouncedSearch] = useState('')

  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current)
    searchTimer.current = setTimeout(() => setDebouncedSearch(search), 300)
    return () => {
      if (searchTimer.current) clearTimeout(searchTimer.current)
    }
  }, [search])

  const loadConversations = useCallback(async () => {
    setLoadingList(true)
    try {
      const res = await axios.get('/api/whatsapp/conversations', {
        params: { status: filter, search: debouncedSearch || undefined, per_page: 50 },
      })
      setConversations(res.data.data ?? [])
    } catch {
      setConversations([])
    } finally {
      setLoadingList(false)
    }
  }, [filter, debouncedSearch])

  const loadStats = useCallback(async () => {
    try {
      const res = await axios.get('/api/whatsapp/stats')
      setStats(res.data)
    } catch {
      // The tab counts are decoration — a failure here must not blank the inbox.
    }
  }, [])

  useEffect(() => {
    loadConversations()
  }, [loadConversations])

  useEffect(() => {
    loadStats()
  }, [loadStats])

  const openConversation = useCallback(async (conversation: ConversationSummary) => {
    setSelectedId(conversation.id)
    setMobileShowThread(true)
    setLoadingDetail(true)
    try {
      const res = await axios.get(`/api/whatsapp/conversations/${conversation.id}`)
      setDetail(res.data)
    } catch {
      setDetail(null)
    } finally {
      setLoadingDetail(false)
    }
  }, [])

  const refreshAll = () => {
    loadConversations()
    loadStats()
    if (selectedId) {
      const current = conversations.find((c) => c.id === selectedId)
      if (current) openConversation(current)
    }
  }

  const statusMeta = detail ? WHATSAPP_STATUS_META[detail.conversation.whatsapp_status] : null

  return (
    <>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main fixed fluid className='flex flex-col'>
        <section className='flex h-full min-h-0 overflow-hidden rounded-lg border bg-background'>
          {/* ── Conversation list ─────────────────────────────────────── */}
          <div
            className={cn(
              'flex w-full min-w-0 flex-col border-e md:w-80 lg:w-96',
              mobileShowThread ? 'hidden md:flex' : 'flex'
            )}
          >
            <div className='space-y-3 border-b p-3'>
              <div className='flex items-center justify-between gap-2'>
                <div className='flex items-center gap-2'>
                  <MessageCircle className='h-5 w-5 text-emerald-600 dark:text-emerald-500' />
                  <h1 className='text-lg font-bold'>WhatsApp</h1>
                  {stats && stats.needs_attention > 0 && (
                    <Badge className='h-5 bg-emerald-600 px-1.5 text-[11px] hover:bg-emerald-600'>
                      {stats.needs_attention}
                    </Badge>
                  )}
                </div>
                <Button
                  size='icon'
                  variant='ghost'
                  className='h-8 w-8'
                  onClick={refreshAll}
                  aria-label='Refresh'
                >
                  <RefreshCw className={cn('h-4 w-4', loadingList && 'animate-spin')} />
                </Button>
              </div>

              <label className='flex h-9 w-full items-center rounded-md border border-input bg-transparent ps-2.5 focus-within:ring-1 focus-within:ring-ring'>
                <SearchIcon className='me-2 h-4 w-4 shrink-0 text-muted-foreground' />
                <span className='sr-only'>Search conversations</span>
                <input
                  type='text'
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder='Name, phone or order number'
                  className='w-full flex-1 bg-transparent pe-2.5 text-sm outline-none placeholder:text-muted-foreground'
                />
              </label>

              <div className='-mx-1 flex gap-1 overflow-x-auto px-1 pb-1'>
                {INBOX_FILTERS.map((tab) => {
                  const count = stats?.[tab.statKey] ?? 0
                  return (
                    <button
                      key={tab.value}
                      type='button'
                      onClick={() => setFilter(tab.value)}
                      className={cn(
                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                        filter === tab.value
                          ? 'bg-emerald-600 text-white'
                          : 'bg-muted text-muted-foreground hover:bg-muted/70'
                      )}
                    >
                      {tab.label}
                      {count > 0 && <span className='ms-1 opacity-70'>{count}</span>}
                    </button>
                  )
                })}
              </div>
            </div>

            <ConversationList
              conversations={conversations}
              selectedId={selectedId}
              onSelect={openConversation}
              loading={loadingList}
            />
          </div>

          {/* ── Thread ────────────────────────────────────────────────── */}
          <div
            className={cn(
              'min-w-0 flex-1 flex-col',
              mobileShowThread ? 'flex' : 'hidden md:flex'
            )}
          >
            {!detail && !loadingDetail ? (
              <div className='flex h-full flex-col items-center justify-center gap-3 p-8 text-center'>
                <div className='rounded-full bg-muted p-4'>
                  <MessageCircle className='h-8 w-8 text-muted-foreground/60' />
                </div>
                <div>
                  <p className='font-medium'>Select a conversation</p>
                  <p className='mt-1 max-w-sm text-sm text-muted-foreground'>
                    Every conversation here started when an agent marked a confirmation call
                    &ldquo;No Answer&rdquo;.
                  </p>
                </div>
              </div>
            ) : (
              <>
                {/* Thread header */}
                <div className='flex items-center gap-3 border-b p-3'>
                  <Button
                    size='icon'
                    variant='ghost'
                    className='h-8 w-8 shrink-0 md:hidden'
                    onClick={() => setMobileShowThread(false)}
                    aria-label='Back to conversations'
                  >
                    <ArrowLeft className='h-4 w-4' />
                  </Button>

                  <Avatar className='h-9 w-9 shrink-0'>
                    <AvatarFallback className='bg-emerald-100 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'>
                      {(detail?.order.customer_name ?? '?')
                        .split(/\s+/)
                        .slice(0, 2)
                        .map((p) => p[0]?.toUpperCase() ?? '')
                        .join('')}
                    </AvatarFallback>
                  </Avatar>

                  <div className='min-w-0 flex-1'>
                    <div className='flex items-center gap-2'>
                      <span className='truncate font-semibold'>
                        {detail?.order.customer_name || 'Unknown customer'}
                      </span>
                      {statusMeta && (
                        <Badge
                          variant='outline'
                          className={cn('h-5 shrink-0 px-1.5 text-[11px]', statusMeta.className)}
                        >
                          {statusMeta.label}
                        </Badge>
                      )}
                    </div>
                    <div className='flex items-center gap-2 text-xs text-muted-foreground'>
                      <span dir='ltr' className='truncate'>
                        {detail?.conversation.phone}
                      </span>
                      {detail?.conversation.replied_at && (
                        <span className='hidden sm:inline'>
                          · replied {format(new Date(detail.conversation.replied_at), 'd MMM, HH:mm')}
                        </span>
                      )}
                    </div>
                  </div>

                  {detail?.order.customer_phone && (
                    <Button
                      size='icon'
                      variant='ghost'
                      className='h-8 w-8 shrink-0'
                      asChild
                      aria-label='Call customer'
                    >
                      <a href={`tel:${detail.order.customer_phone}`}>
                        <Phone className='h-4 w-4' />
                      </a>
                    </Button>
                  )}

                  <Button
                    size='icon'
                    variant='ghost'
                    className='hidden h-8 w-8 shrink-0 xl:inline-flex'
                    onClick={() => setShowContext((v) => !v)}
                    aria-label={showContext ? 'Hide order details' : 'Show order details'}
                  >
                    {showContext ? (
                      <PanelRightClose className='h-4 w-4' />
                    ) : (
                      <PanelRightOpen className='h-4 w-4' />
                    )}
                  </Button>
                </div>

                <div className='flex min-h-0 flex-1 bg-muted/30'>
                  <div className='flex min-w-0 flex-1 flex-col'>
                    <MessageThread messages={detail?.messages ?? []} loading={loadingDetail} />
                  </div>
                </div>

                {/* Below xl the order panel can't fit beside the thread, so it
                    stacks underneath rather than being lost entirely. */}
                {detail && (
                  <div className='max-h-64 shrink-0 overflow-hidden border-t xl:hidden'>
                    <OrderContextPanel order={detail.order} />
                  </div>
                )}
              </>
            )}
          </div>

          {/* ── Order context (xl and up) ─────────────────────────────── */}
          {detail && showContext && (
            <div className='hidden w-80 shrink-0 border-s xl:block'>
              <OrderContextPanel order={detail.order} />
            </div>
          )}
        </section>
      </Main>
    </>
  )
}
