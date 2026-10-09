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
import { EmptyState } from '@/components/empty-state'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { PinLock } from '@/components/pin-lock'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { WHATSAPP_STATUS_META } from '@/features/orders/data/call-status'
import { isWhatsAppLocked, lockWhatsApp, unlockWhatsApp } from './api'
import { ConversationList } from './components/conversation-list'
import { InboxStats } from './components/inbox-stats'
import { MessagingToggle } from './components/messaging-toggle'
import { MessageThread } from './components/message-thread'
import { OrderContextPanel } from './components/order-context-panel'
import { ReplyComposer } from './components/reply-composer'
import { usePermissions } from '@/hooks/use-permissions'
import {
  INBOX_FILTERS,
  type ConversationDetail,
  type ConversationSummary,
  type ThreadMessage,
  type InboxStats as InboxStatsData,
} from './types'

export function WhatsAppInbox() {
  // Always starts locked, like the recycle bin. Component state rather than
  // storage, so every visit to the inbox asks for the PIN.
  // The numbers and the conversations are each a permission of their own;
  // only the conversations sit behind the PIN.
  const { can } = usePermissions()
  const canStats = can('view whatsapp stats')
  const canConversations = can('view whatsapp conversations')
  const [unlocked, setUnlocked] = useState(false)
  const [lockNotice, setLockNotice] = useState<string | null>(null)

  const [conversations, setConversations] = useState<ConversationSummary[]>([])
  const [stats, setStats] = useState<InboxStatsData | null>(null)
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

  // Re-lock on the way out so the next visit cannot inherit this unlock.
  useEffect(() => {
    return () => {
      void lockWhatsApp()
    }
  }, [])

  // Any 423 drops straight back to the PIN prompt, with nothing left behind it.
  const handleLocked = useCallback(() => {
    setUnlocked(false)
    setLockNotice('Your WhatsApp session expired. Enter the PIN again.')
    setConversations([])
    setSelectedId(null)
    setDetail(null)
    setMobileShowThread(false)
  }, [])

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
    if (!unlocked) return
    setLoadingList(true)
    try {
      const res = await axios.get('/api/whatsapp/conversations', {
        params: { status: filter, search: debouncedSearch || undefined, per_page: 50 },
      })
      setConversations(res.data.data ?? [])
    } catch (err) {
      setConversations([])
      if (isWhatsAppLocked(err)) handleLocked()
    } finally {
      setLoadingList(false)
    }
  }, [unlocked, filter, debouncedSearch, handleLocked])

  const loadStats = useCallback(async () => {
    if (!canStats || (canConversations && !unlocked)) return
    try {
      const res = await axios.get('/api/whatsapp/stats')
      setStats(res.data)
    } catch {
      // The tab counts are decoration — a failure here must not blank the inbox.
    }
  }, [unlocked])

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
    } catch (err) {
      setDetail(null)
      if (isWhatsAppLocked(err)) handleLocked()
    } finally {
      setLoadingDetail(false)
    }
  }, [handleLocked])

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

      <Main fixed fluid className='flex flex-col gap-3'>
        <PinLock
          open={canConversations && !unlocked}
          title='WhatsApp is locked'
          description='Enter the PIN to unlock the WhatsApp inbox.'
          unlock={unlockWhatsApp}
          notice={lockNotice}
          onUnlocked={() => {
            setLockNotice(null)
            setUnlocked(true)
          }}
        />

        {/* Nothing below loads until the PIN is accepted, so there is no
            data behind the prompt. */}
        {(unlocked || !canConversations) && (
          <>
            {unlocked && <MessagingToggle onLocked={handleLocked} />}

            {canStats && <InboxStats stats={stats} />}

            {unlocked && (
            <section className='flex min-h-0 flex-1 overflow-hidden rounded-lg border bg-background'>
              {/* ── Conversation list ─────────────────────────────────────── */}
              <div
                className={cn(
                  'flex w-full min-h-0 min-w-0 flex-col border-e md:w-64 md:shrink-0 lg:w-72 xl:w-80',
                  mobileShowThread ? 'hidden md:flex' : 'flex'
                )}
              >
                <div className='space-y-3 border-b p-3'>
                  <div className='flex items-center justify-between gap-2'>
                    <div className='flex items-center gap-2'>
                      <MessageCircle className='h-5 w-5 text-emerald-600 dark:text-emerald-500' />
                      <h1 className='text-base font-bold'>Inbox</h1>
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
                  <EmptyState
                    bot='blob'
                    size='lg'
                    className='h-full p-8'
                    title='Select a conversation'
                    description={
                      <>
                        Every conversation here started when an agent marked a confirmation call
                        &ldquo;No Answer&rdquo;.
                      </>
                    }
                  />
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

                    {detail && (
                      <ReplyComposer
                        orderId={detail.order.id}
                        windowOpen={detail.window?.open ?? false}
                        windowExpiresAt={detail.window?.expires_at ?? null}
                        onSent={(message) => {
                          // Append locally so the bubble appears instantly; the
                          // list still refreshes for the preview and ordering.
                          setDetail((prev) =>
                            prev ? { ...prev, messages: [...prev.messages, message] } : prev
                          )
                          loadConversations()
                        }}
                        onLocked={handleLocked}
                      />
                    )}

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
            )}
          </>
        )}
      </Main>
    </>
  )
}
