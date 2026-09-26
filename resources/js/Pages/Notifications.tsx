import { useState } from 'react'
import { Head, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Pagination } from '@/components/data-table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Bell, Check, CheckCheck, Trash2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { usePermissions } from '@/hooks/use-permissions'
import axios from 'axios'

interface Notification {
  id: string
  type: string
  data: {
    title: string
    message: string
    type: string
    icon?: string
    action_url?: string
  }
  read_at: string | null
  created_at: string
}

interface PaginatedNotifications {
  data: Notification[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export default function NotificationsPage({
  notifications: initialNotifications,
}: {
  notifications: PaginatedNotifications
}) {
  const [notifications, setNotifications] = useState<Notification[]>(
    initialNotifications.data || []
  )
  const [pagination, setPagination] = useState({
    current_page: initialNotifications.current_page,
    last_page: initialNotifications.last_page,
    total: initialNotifications.total,
    from: initialNotifications.from,
    to: initialNotifications.to,
    per_page: initialNotifications.per_page,
  })
  const [filter, setFilter] = useState<'all' | 'unread'>('all')
  const [loading, setLoading] = useState(false)
  const [perPage, setPerPage] = useState(initialNotifications.per_page)
  const { can } = usePermissions()

  const fetchNotifications = async (page: number = 1, newPerPage?: number) => {
    try {
      setLoading(true)
      const itemsPerPage = newPerPage || perPage
      const response = await axios.get(
        `/api/notifications?page=${page}&per_page=${itemsPerPage}`
      )
      setNotifications(response.data.data || [])
      setPagination({
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        total: response.data.total,
        from: response.data.from,
        to: response.data.to,
        per_page: response.data.per_page,
      })
    } catch (error) {
      console.error('Failed to fetch notifications:', error)
    } finally {
      setLoading(false)
    }
  }

  const markAsRead = async (id: string) => {
    try {
      await axios.post(`/api/notifications/${id}/read`)
      setNotifications(
        notifications.map((n) =>
          n.id === id ? { ...n, read_at: new Date().toISOString() } : n
        )
      )
    } catch (error) {
      console.error('Failed to mark notification as read:', error)
    }
  }

  const markAllAsRead = async () => {
    try {
      setLoading(true)
      await axios.post('/api/notifications/read-all')
      setNotifications(
        notifications.map((n) => ({ ...n, read_at: new Date().toISOString() }))
      )
    } catch (error) {
      console.error('Failed to mark all as read:', error)
    } finally {
      setLoading(false)
    }
  }

  const deleteNotification = async (id: string) => {
    try {
      await axios.delete(`/api/notifications/${id}`)
      setNotifications(notifications.filter((n) => n.id !== id))
      // Refresh to get updated pagination
      if (notifications.length === 1 && pagination.current_page > 1) {
        fetchNotifications(pagination.current_page - 1)
      } else {
        fetchNotifications(pagination.current_page)
      }
    } catch (error) {
      console.error('Failed to delete notification:', error)
    }
  }

  const getTimeAgo = (date: string) => {
    const now = new Date()
    const notificationDate = new Date(date)
    const seconds = Math.floor((now.getTime() - notificationDate.getTime()) / 1000)

    if (seconds < 60) return 'Just now'
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
    return notificationDate.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }

  const handlePageChange = (page: number) => {
    fetchNotifications(page)
  }

  const handlePerPageChange = (newPerPage: number) => {
    setPerPage(newPerPage)
    fetchNotifications(1, newPerPage)
  }

  const filteredNotifications =
    filter === 'unread'
      ? notifications.filter((n) => !n.read_at)
      : notifications

  const unreadCount = notifications.filter((n) => !n.read_at).length

  return (
    <AuthenticatedLayout>
      <Head title='Notifications' />

      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='mb-2 flex items-center justify-between'>
          <h1 className='text-2xl font-bold tracking-tight'>Notifications</h1>
          <div className='flex items-center gap-2'>
            {unreadCount > 0 && (
              <Button
                variant='outline'
                size='sm'
                onClick={markAllAsRead}
                disabled={loading}
              >
                <CheckCheck className='mr-2 h-4 w-4' />
                Mark all read
              </Button>
            )}
          </div>
        </div>
        <p className='text-muted-foreground'>
          Manage your notifications and stay updated
        </p>

        <Tabs
          defaultValue='all'
          value={filter}
          onValueChange={(v) => setFilter(v as any)}
          className='mt-6'
        >
          <TabsList>
            <TabsTrigger value='all'>All ({pagination.total})</TabsTrigger>
            <TabsTrigger value='unread'>
              Unread{' '}
              {unreadCount > 0 && (
                <Badge variant='secondary' className='ml-1'>
                  {unreadCount}
                </Badge>
              )}
            </TabsTrigger>
          </TabsList>

          <TabsContent value={filter} className='mt-4'>
            {filteredNotifications.length === 0 ? (
              <div className='flex flex-col items-center justify-center rounded-lg border border-dashed py-12'>
                <Bell className='mb-4 h-12 w-12 text-muted-foreground/30' />
                <h3 className='mb-1 text-lg font-medium'>No notifications</h3>
                <p className='text-sm text-muted-foreground'>
                  {filter === 'unread'
                    ? "You're all caught up!"
                    : 'Notifications will appear here'}
                </p>
              </div>
            ) : (
              <>
                <div className='space-y-2'>
                  {filteredNotifications.map((notification) => (
                    <div
                      key={notification.id}
                      className={cn(
                        'group rounded-lg border p-4 transition-colors hover:bg-muted/50',
                        !notification.read_at &&
                          'border-l-2 border-l-blue-500 bg-muted/30'
                      )}
                    >
                      <div className='flex items-start gap-3'>
                        {!notification.read_at && (
                          <div className='mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-500' />
                        )}
                        <div className='flex-1 space-y-1'>
                          <div className='flex items-center justify-between gap-4'>
                            <h4 className='text-sm font-semibold'>
                              {notification.data.title}
                            </h4>
                            <span className='text-xs text-muted-foreground'>
                              {getTimeAgo(notification.created_at)}
                            </span>
                          </div>
                          <p className='text-sm text-muted-foreground'>
                            {notification.data.message}
                          </p>
                        </div>
                        <div className='flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100'>
                          {!notification.read_at && (
                            <Button
                              variant='ghost'
                              size='icon'
                              className='h-8 w-8'
                              onClick={() => markAsRead(notification.id)}
                              title='Mark as read'
                            >
                              <Check className='h-4 w-4' />
                            </Button>
                          )}
                          {can('delete notifications') && (
                            <Button
                              variant='ghost'
                              size='icon'
                              className='h-8 w-8 text-destructive hover:text-destructive'
                              onClick={() => deleteNotification(notification.id)}
                              title='Delete'
                            >
                              <Trash2 className='h-4 w-4' />
                            </Button>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                <Pagination
                  meta={pagination}
                  onPageChange={handlePageChange}
                  onPageSizeChange={handlePerPageChange}
                  disabled={loading}
                  className='mt-6 border-t pt-4'
                />
              </>
            )}
          </TabsContent>
        </Tabs>
      </Main>
    </AuthenticatedLayout>
  )
}
