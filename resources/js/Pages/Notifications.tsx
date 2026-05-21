import { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  DoubleArrowLeftIcon,
  DoubleArrowRightIcon,
} from '@radix-ui/react-icons'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Bell, Check, CheckCheck, Trash2, ExternalLink } from 'lucide-react'
import { cn, getPageNumbers } from '@/lib/utils'
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

  const handlePerPageChange = (value: string) => {
    const newPerPage = Number(value)
    setPerPage(newPerPage)
    fetchNotifications(1, newPerPage)
  }

  const filteredNotifications =
    filter === 'unread'
      ? notifications.filter((n) => !n.read_at)
      : notifications

  const unreadCount = notifications.filter((n) => !n.read_at).length
  const pageNumbers = getPageNumbers(pagination.current_page, pagination.last_page)

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
                          {notification.data.action_url && (
                            <Link href={notification.data.action_url}>
                              <Button
                                variant='ghost'
                                size='icon'
                                className='h-8 w-8'
                                title='View'
                              >
                                <ExternalLink className='h-4 w-4' />
                              </Button>
                            </Link>
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

                {/* Pagination - Same as Orders page */}
                {pagination.last_page > 1 && (
                  <div className='mt-6 flex items-center justify-between overflow-clip border-t pt-4'>
                    <div className='flex w-full items-center justify-between'>
                      <div className='flex w-25 items-center justify-center text-sm font-medium md:hidden'>
                        Page {pagination.current_page} of {pagination.last_page}
                      </div>
                      <div className='flex items-center gap-2'>
                        <Select value={`${perPage}`} onValueChange={handlePerPageChange}>
                          <SelectTrigger className='h-8 w-17.5'>
                            <SelectValue placeholder={perPage} />
                          </SelectTrigger>
                          <SelectContent side='top'>
                            {[10, 20, 30, 40, 50].map((pageSize) => (
                              <SelectItem key={pageSize} value={`${pageSize}`}>
                                {pageSize}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <p className='hidden text-sm font-medium sm:block'>
                          Rows per page
                        </p>
                      </div>
                    </div>

                    <div className='flex items-center space-x-6 lg:space-x-8'>
                      <div className='hidden w-25 items-center justify-center text-sm font-medium md:flex'>
                        Page {pagination.current_page} of {pagination.last_page}
                      </div>
                      <div className='flex items-center space-x-2'>
                        <Button
                          variant='outline'
                          className='size-8 p-0 max-md:hidden'
                          onClick={() => handlePageChange(1)}
                          disabled={pagination.current_page === 1 || loading}
                        >
                          <span className='sr-only'>Go to first page</span>
                          <DoubleArrowLeftIcon className='h-4 w-4' />
                        </Button>
                        <Button
                          variant='outline'
                          className='size-8 p-0'
                          onClick={() =>
                            handlePageChange(pagination.current_page - 1)
                          }
                          disabled={pagination.current_page === 1 || loading}
                        >
                          <span className='sr-only'>Go to previous page</span>
                          <ChevronLeftIcon className='h-4 w-4' />
                        </Button>

                        {/* Page number buttons */}
                        {pageNumbers.map((pageNumber, index) => (
                          <div key={`${pageNumber}-${index}`} className='flex items-center'>
                            {pageNumber === '...' ? (
                              <span className='px-1 text-sm text-muted-foreground'>
                                ...
                              </span>
                            ) : (
                              <Button
                                variant={
                                  pagination.current_page === pageNumber
                                    ? 'default'
                                    : 'outline'
                                }
                                className='h-8 min-w-8 px-2'
                                onClick={() =>
                                  handlePageChange(pageNumber as number)
                                }
                                disabled={loading}
                              >
                                <span className='sr-only'>
                                  Go to page {pageNumber}
                                </span>
                                {pageNumber}
                              </Button>
                            )}
                          </div>
                        ))}

                        <Button
                          variant='outline'
                          className='size-8 p-0'
                          onClick={() =>
                            handlePageChange(pagination.current_page + 1)
                          }
                          disabled={
                            pagination.current_page === pagination.last_page ||
                            loading
                          }
                        >
                          <span className='sr-only'>Go to next page</span>
                          <ChevronRightIcon className='h-4 w-4' />
                        </Button>
                        <Button
                          variant='outline'
                          className='size-8 p-0 max-md:hidden'
                          onClick={() => handlePageChange(pagination.last_page)}
                          disabled={
                            pagination.current_page === pagination.last_page ||
                            loading
                          }
                        >
                          <span className='sr-only'>Go to last page</span>
                          <DoubleArrowRightIcon className='h-4 w-4' />
                        </Button>
                      </div>
                    </div>
                  </div>
                )}
              </>
            )}
          </TabsContent>
        </Tabs>
      </Main>
    </AuthenticatedLayout>
  )
}
