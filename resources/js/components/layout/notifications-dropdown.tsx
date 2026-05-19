import { useState, useEffect } from 'react'
import { Link, router } from '@inertiajs/react'
import { Bell, Check, CheckCheck, Trash2, ExternalLink } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { cn } from '@/lib/utils'
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

export function NotificationsDropdown() {
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [loading, setLoading] = useState(false)

  const fetchNotifications = async () => {
    try {
      const response = await axios.get('/api/notifications?per_page=15')
      setNotifications(response.data.data?.slice(0, 15) || [])
    } catch (error) {
      console.error('Failed to fetch notifications:', error)
    }
  }

  const fetchUnreadCount = async () => {
    try {
      const response = await axios.get('/api/notifications/unread-count')
      setUnreadCount(response.data.count || 0)
    } catch (error) {
      console.error('Failed to fetch unread count:', error)
    }
  }

  useEffect(() => {
    fetchNotifications()
    fetchUnreadCount()

    const interval = setInterval(() => {
      fetchUnreadCount()
    }, 30000)

    return () => clearInterval(interval)
  }, [])

  const markAsRead = async (id: string) => {
    try {
      await axios.post(`/api/notifications/${id}/read`)
      setNotifications(
        notifications.map((n) =>
          n.id === id ? { ...n, read_at: new Date().toISOString() } : n
        )
      )
      setUnreadCount(Math.max(0, unreadCount - 1))
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
      setUnreadCount(0)
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
      if (notifications.find((n) => n.id === id)?.read_at === null) {
        setUnreadCount(Math.max(0, unreadCount - 1))
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
    return notificationDate.toLocaleDateString()
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' size='icon' className='relative'>
          <Bell className='h-5 w-5' />
          {unreadCount > 0 && (
            <Badge
              variant='destructive'
              className='absolute -right-1 -top-1 h-5 min-w-5 rounded-full px-1 text-xs'
            >
              {unreadCount > 99 ? '99+' : unreadCount}
            </Badge>
          )}
          <span className='sr-only'>Notifications</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-80'>
        <div className='flex items-center justify-between p-2'>
          <h3 className='text-sm font-semibold'>Notifications</h3>
          {unreadCount > 0 && (
            <Button
              variant='ghost'
              size='sm'
              onClick={markAllAsRead}
              disabled={loading}
              className='h-auto p-1 text-xs'
            >
              <CheckCheck className='mr-1 h-3 w-3' />
              Mark all read
            </Button>
          )}
        </div>
        <DropdownMenuSeparator />
        <ScrollArea className='h-[400px]'>
          <div className='px-1'>
            {notifications.length === 0 ? (
              <div className='p-4 text-center text-sm text-muted-foreground'>
                No notifications
              </div>
            ) : (
              notifications.map((notification) => (
              <div
                key={notification.id}
                className={cn(
                  'group relative border-b p-3 transition-colors hover:bg-accent',
                  !notification.read_at && 'bg-muted/50'
                )}
              >
                <div className='flex gap-3'>
                  {!notification.read_at && (
                    <div className='mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-500' />
                  )}
                  <div className='flex-1 space-y-1'>
                    <p className='text-sm font-medium leading-none'>
                      {notification.data.title}
                    </p>
                    <p className='text-xs text-muted-foreground'>
                      {notification.data.message}
                    </p>
                    <p className='text-xs text-muted-foreground'>
                      {getTimeAgo(notification.created_at)}
                    </p>
                  </div>
                  <div className='flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100'>
                    {!notification.read_at && (
                      <Button
                        variant='ghost'
                        size='icon'
                        className='h-7 w-7'
                        onClick={() => markAsRead(notification.id)}
                        title='Mark as read'
                      >
                        <Check className='h-3.5 w-3.5' />
                      </Button>
                    )}
                    {notification.data.action_url && (
                      <Link href={notification.data.action_url}>
                        <Button
                          variant='ghost'
                          size='icon'
                          className='h-7 w-7'
                          title='View'
                        >
                          <ExternalLink className='h-3.5 w-3.5' />
                        </Button>
                      </Link>
                    )}
                    <Button
                      variant='ghost'
                      size='icon'
                      className='h-7 w-7 text-destructive'
                      onClick={() => deleteNotification(notification.id)}
                      title='Delete'
                    >
                      <Trash2 className='h-3.5 w-3.5' />
                    </Button>
                  </div>
                </div>
              </div>
              ))
            )}
          </div>
        </ScrollArea>
        {notifications.length > 0 && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link
                href='/notifications'
                className='w-full cursor-pointer text-center text-sm font-medium'
              >
                View all notifications
              </Link>
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
