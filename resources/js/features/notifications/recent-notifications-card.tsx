import { useState, useEffect } from 'react'
import { Link } from '@inertiajs/react'
import { Bell } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import axios from 'axios'

interface Notification {
  id: string
  type: string
  data: {
    title: string
    message: string
    type: string
    action_url?: string
  }
  read_at: string | null
  created_at: string
}

export function RecentNotificationsCard() {
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchNotifications = async () => {
      try {
        const response = await axios.get('/api/notifications')
        setNotifications(response.data.data?.slice(0, 3) || [])
      } catch (error) {
        console.error('Failed to fetch notifications:', error)
      } finally {
        setLoading(false)
      }
    }

    fetchNotifications()
  }, [])

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

  const unreadCount = notifications.filter((n) => !n.read_at).length

  return (
    <Card>
      <CardHeader>
        <div className='flex items-center justify-between'>
          <div className='space-y-1'>
            <CardTitle className='flex items-center gap-2'>
              <Bell className='h-5 w-5' />
              Recent Notifications
              {unreadCount > 0 && (
                <Badge variant='secondary' className='ml-1'>
                  {unreadCount} new
                </Badge>
              )}
            </CardTitle>
            <CardDescription>Your latest activity updates</CardDescription>
          </div>
          <Link href='/notifications'>
            <Button variant='outline' size='sm'>
              View all
            </Button>
          </Link>
        </div>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className='space-y-3'>
            {[1, 2, 3].map((i) => (
              <div key={i} className='animate-pulse'>
                <div className='h-4 w-3/4 rounded bg-muted' />
                <div className='mt-2 h-3 w-1/2 rounded bg-muted' />
              </div>
            ))}
          </div>
        ) : notifications.length === 0 ? (
          <div className='py-8 text-center'>
            <Bell className='mx-auto mb-2 h-12 w-12 text-muted-foreground/30' />
            <p className='text-sm text-muted-foreground'>No notifications yet</p>
          </div>
        ) : (
          <div className='space-y-4'>
            {notifications.map((notification) => (
              <div
                key={notification.id}
                className={cn(
                  'group relative rounded-lg border p-3 transition-colors hover:bg-muted/50',
                  !notification.read_at && 'border-l-2 border-l-blue-500 bg-muted/30'
                )}
              >
                <div className='flex items-start gap-3'>
                  <div className='flex-1 space-y-1'>
                    <div className='flex items-center gap-2'>
                      <p className='text-sm font-medium leading-none'>
                        {notification.data.title}
                      </p>
                      {!notification.read_at && (
                        <div className='h-1.5 w-1.5 rounded-full bg-blue-500' />
                      )}
                    </div>
                    <p className='text-xs text-muted-foreground'>
                      {notification.data.message}
                    </p>
                    <p className='text-xs text-muted-foreground'>
                      {getTimeAgo(notification.created_at)}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
