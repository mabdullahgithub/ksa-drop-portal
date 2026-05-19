import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Mail, CheckCircle, XCircle, Clock, TrendingUp } from 'lucide-react'

interface EmailStats {
  total_sent: number
  total_failed: number
  today_sent: number
  this_week: number
  by_type: Record<string, number>
}

export function EmailStatistics() {
  const [stats, setStats] = useState<EmailStats | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchStatistics()
  }, [])

  const fetchStatistics = async () => {
    try {
      const response = await fetch(route('admin.email-statistics'))
      const data = await response.json()
      setStats(data)
    } catch (error) {
      console.error('Failed to fetch statistics:', error)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
        {[...Array(4)].map((_, i) => (
          <Card key={i}>
            <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
              <CardTitle className='text-sm font-medium'>Loading...</CardTitle>
            </CardHeader>
            <CardContent>
              <div className='h-8 bg-muted animate-pulse rounded' />
            </CardContent>
          </Card>
        ))}
      </div>
    )
  }

  if (!stats) {
    return <div>Failed to load statistics</div>
  }

  const successRate = stats.total_sent + stats.total_failed > 0
    ? ((stats.total_sent / (stats.total_sent + stats.total_failed)) * 100).toFixed(1)
    : 0

  return (
    <div className='space-y-4'>
      {/* Overview Cards */}
      <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>Total Sent</CardTitle>
            <CheckCircle className='h-4 w-4 text-green-600' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>{stats.total_sent.toLocaleString()}</div>
            <p className='text-xs text-muted-foreground mt-1'>
              {successRate}% success rate
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>Failed</CardTitle>
            <XCircle className='h-4 w-4 text-red-600' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>{stats.total_failed.toLocaleString()}</div>
            <p className='text-xs text-muted-foreground mt-1'>
              All time failures
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>Today</CardTitle>
            <Clock className='h-4 w-4 text-blue-600' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>{stats.today_sent.toLocaleString()}</div>
            <p className='text-xs text-muted-foreground mt-1'>
              Emails sent today
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>This Week</CardTitle>
            <TrendingUp className='h-4 w-4 text-purple-600' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>{stats.this_week.toLocaleString()}</div>
            <p className='text-xs text-muted-foreground mt-1'>
              Weekly total
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Email Types Breakdown */}
      <Card>
        <CardHeader>
          <CardTitle>Emails by Type</CardTitle>
          <CardDescription>
            Breakdown of emails sent by category
          </CardDescription>
        </CardHeader>
        <CardContent>
          {Object.keys(stats.by_type).length > 0 ? (
            <div className='space-y-4'>
              {Object.entries(stats.by_type)
                .sort(([, a], [, b]) => b - a)
                .map(([type, count]) => (
                  <div key={type} className='flex items-center justify-between'>
                    <div className='flex items-center gap-3'>
                      <Mail className='h-4 w-4 text-muted-foreground' />
                      <span className='text-sm font-medium capitalize'>
                        {type.replace(/_/g, ' ')}
                      </span>
                    </div>
                    <div className='flex items-center gap-4'>
                      <div className='w-32 bg-muted rounded-full h-2'>
                        <div
                          className='bg-primary h-2 rounded-full'
                          style={{
                            width: `${(count / Math.max(...Object.values(stats.by_type))) * 100}%`,
                          }}
                        />
                      </div>
                      <span className='text-sm font-mono text-muted-foreground min-w-[3rem] text-right'>
                        {count}
                      </span>
                    </div>
                  </div>
                ))}
            </div>
          ) : (
            <p className='text-sm text-muted-foreground text-center py-8'>
              No emails sent yet
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
