import { Head, Link } from '@inertiajs/react'
import { useState, useEffect } from 'react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Package, TrendingUp, AlertTriangle, RotateCcw, Banknote, CheckCircle2, Clock, Activity,
  ArrowLeft, ShieldCheck
} from 'lucide-react'
import axios from 'axios'

interface Analytics {
  period_days: number
  totals: {
    total: number
    delivered: number
    returned: number
    exceptions: number
    cancelled: number
    in_transit: number
    avg_delivery_days: number | null
  }
  by_status: Array<{ status: string; count: number }>
  daily: Array<{ date: string; created: number; delivered: number }>
  exceptions: Array<{
    id: number
    order_id: number
    tracking_number: string | null
    courier_status_description: string | null
    exception_note: string | null
    exception_escalated_at: string | null
    created_at: string
    order: { id: number; order_number: string; customer_name: string } | null
  }>
  returns: Array<{
    id: number
    order_id: number
    tracking_number: string | null
    cancel_reason: string | null
    cancelled_at: string | null
    order: { id: number; order_number: string; customer_name: string } | null
  }>
  cod: {
    summary: { total_collected: number; total_amount: number } | null
    transactions: Array<{
      id: number
      tracking_number: string | null
      order_number: string
      customer_name: string
      total: number
      cod_collected_amount: number
      cod_collected_at: string | null
      financial_status: string
    }>
  }
}

const statusColorMap: Record<string, string> = {
  pending: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
  info_received: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  in_transit: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
  out_for_delivery: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  attempt_fail: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  exception: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  returned: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
}

function fmt(v: number | null | undefined) {
  return v != null ? v.toLocaleString() : '—'
}

function fmtDate(v: string | null | undefined) {
  if (!v) return '—'
  try { return new Date(v).toLocaleDateString() } catch { return '—' }
}

function StatCard({
  title, value, sub, icon: Icon, color,
}: { title: string; value: string | number; sub?: string; icon: any; color: string }) {
  return (
    <Card>
      <CardHeader className='flex flex-row items-center justify-between pb-2'>
        <CardTitle className='text-sm font-medium text-muted-foreground'>{title}</CardTitle>
        <Icon className={`h-4 w-4 ${color}`} />
      </CardHeader>
      <CardContent>
        <div className='text-2xl font-bold'>{value}</div>
        {sub && <p className='text-xs text-muted-foreground mt-1'>{sub}</p>}
      </CardContent>
    </Card>
  )
}

export default function ShipmentAnalyticsPage() {
  const [days, setDays] = useState('30')
  const [data, setData] = useState<Analytics | null>(null)
  const [loading, setLoading] = useState(true)
  const [healthStatus, setHealthStatus] = useState<'healthy' | 'degraded' | 'error' | null>(null)
  const [checkingHealth, setCheckingHealth] = useState(false)

  const load = async (d: string) => {
    setLoading(true)
    try {
      const res = await axios.get(`/api/shipments-analytics?days=${d}`)
      setData(res.data)
    } catch {
      // silently fail, UI shows empty
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load(days) }, [days])

  const checkHealth = async () => {
    setCheckingHealth(true)
    setHealthStatus(null)
    try {
      const res = await axios.get('/api/jnt/health')
      setHealthStatus(res.data.status as any)
    } catch (err: any) {
      setHealthStatus(err.response?.data?.status ?? 'error')
    } finally {
      setCheckingHealth(false)
    }
  }

  const t = data?.totals
  const deliveryRate = t && t.total > 0 ? Math.round((t.delivered / t.total) * 100) : 0
  const returnRate = t && t.total > 0 ? Math.round((t.returned / t.total) * 100) : 0

  return (
    <AuthenticatedLayout>
      <Head title='Shipment Analytics' />
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>
      <Main>
        <div className='mb-6 flex items-center justify-between'>
          <div className='flex items-center gap-3'>
            <Link href='/apps/jnt-express'>
              <Button variant='ghost' size='sm'>
                <ArrowLeft className='h-4 w-4 mr-1' /> Back to J&T Settings
              </Button>
            </Link>
            <div>
              <h1 className='text-2xl font-bold tracking-tight'>Shipment Analytics</h1>
              <p className='text-muted-foreground text-sm'>J&T Express performance dashboard</p>
            </div>
          </div>

          <div className='flex items-center gap-3'>
            <Select value={days} onValueChange={setDays}>
              <SelectTrigger className='w-36'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value='7'>Last 7 days</SelectItem>
                <SelectItem value='14'>Last 14 days</SelectItem>
                <SelectItem value='30'>Last 30 days</SelectItem>
                <SelectItem value='90'>Last 90 days</SelectItem>
              </SelectContent>
            </Select>

            <Button variant='outline' size='sm' onClick={checkHealth} disabled={checkingHealth}>
              <Activity className='h-4 w-4 mr-1' />
              {checkingHealth ? 'Checking...' : 'API Health'}
              {healthStatus && (
                <span className={`ml-2 h-2 w-2 rounded-full ${
                  healthStatus === 'healthy' ? 'bg-green-500' : 'bg-red-500'
                }`} />
              )}
            </Button>
          </div>
        </div>

        {/* API Health Banner */}
        {healthStatus && (
          <div className={`mb-4 rounded-md px-4 py-3 text-sm flex items-center gap-2 ${
            healthStatus === 'healthy'
              ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-400'
              : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-400'
          }`}>
            {healthStatus === 'healthy'
              ? <><CheckCircle2 className='h-4 w-4' /> J&T Express API is healthy and reachable.</>
              : <><AlertTriangle className='h-4 w-4' /> J&T Express API is unreachable or returning errors.</>
            }
          </div>
        )}

        {/* KPI Cards */}
        <div className='grid grid-cols-2 md:grid-cols-4 gap-4 mb-6'>
          {loading ? (
            Array.from({ length: 8 }).map((_, i) => (
              <Card key={i}><CardContent className='pt-6'><Skeleton className='h-8 w-20 mb-2' /><Skeleton className='h-4 w-32' /></CardContent></Card>
            ))
          ) : (
            <>
              <StatCard title='Total Shipments' value={fmt(t?.total)} icon={Package} color='text-blue-500' />
              <StatCard title='Delivered' value={fmt(t?.delivered)} sub={`${deliveryRate}% delivery rate`} icon={CheckCircle2} color='text-green-500' />
              <StatCard title='In Transit' value={fmt(t?.in_transit)} icon={TrendingUp} color='text-indigo-500' />
              <StatCard title='Avg. Delivery' value={t?.avg_delivery_days != null ? `${t.avg_delivery_days.toFixed(1)} days` : '—'} icon={Clock} color='text-orange-500' />
              <StatCard title='Returns' value={fmt(t?.returned)} sub={`${returnRate}% return rate`} icon={RotateCcw} color='text-purple-500' />
              <StatCard title='Exceptions' value={fmt(t?.exceptions)} icon={AlertTriangle} color='text-red-500' />
              <StatCard title='Cancelled' value={fmt(t?.cancelled)} icon={X} color='text-gray-500' />
              <StatCard
                title='COD Collected'
                value={data?.cod.summary ? `SAR ${Number(data.cod.summary.total_amount).toLocaleString()}` : '—'}
                sub={data?.cod.summary ? `${fmt(data.cod.summary.total_collected)} shipments` : undefined}
                icon={Banknote}
                color='text-amber-500'
              />
            </>
          )}
        </div>

        {/* Tabs for details */}
        <Tabs defaultValue='overview'>
          <TabsList>
            <TabsTrigger value='overview'>By Status</TabsTrigger>
            <TabsTrigger value='daily'>Daily Trend</TabsTrigger>
            <TabsTrigger value='exceptions'>
              Exceptions {!loading && t?.exceptions ? <span className='ml-1 text-red-500'>({t.exceptions})</span> : null}
            </TabsTrigger>
            <TabsTrigger value='returns'>Returns</TabsTrigger>
            <TabsTrigger value='cod'>COD Remittances</TabsTrigger>
          </TabsList>

          {/* By Status */}
          <TabsContent value='overview'>
            <Card>
              <CardHeader>
                <CardTitle>Shipments by Status</CardTitle>
                <CardDescription>All-time breakdown for the selected period</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className='space-y-2'>{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className='h-6 w-full' />)}</div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Status</TableHead>
                        <TableHead className='text-right'>Count</TableHead>
                        <TableHead className='text-right'>% of total</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data?.by_status.map(row => (
                        <TableRow key={row.status}>
                          <TableCell>
                            <Badge variant='outline' className={`capitalize text-xs ${statusColorMap[row.status] ?? ''}`}>
                              {row.status.replace(/_/g, ' ')}
                            </Badge>
                          </TableCell>
                          <TableCell className='text-right font-medium'>{fmt(row.count)}</TableCell>
                          <TableCell className='text-right text-muted-foreground'>
                            {t?.total ? Math.round((row.count / t.total) * 100) : 0}%
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* Daily Trend */}
          <TabsContent value='daily'>
            <Card>
              <CardHeader>
                <CardTitle>Daily Shipment Trend</CardTitle>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <Skeleton className='h-48 w-full' />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead className='text-right'>Created</TableHead>
                        <TableHead className='text-right'>Delivered</TableHead>
                        <TableHead className='text-right'>Delivery %</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data?.daily.map(row => (
                        <TableRow key={row.date}>
                          <TableCell>{row.date}</TableCell>
                          <TableCell className='text-right'>{row.created}</TableCell>
                          <TableCell className='text-right text-green-600'>{row.delivered}</TableCell>
                          <TableCell className='text-right text-muted-foreground'>
                            {row.created > 0 ? Math.round((row.delivered / row.created) * 100) : 0}%
                          </TableCell>
                        </TableRow>
                      ))}
                      {data?.daily.length === 0 && (
                        <TableRow><TableCell colSpan={4} className='text-center text-muted-foreground py-8'>No data</TableCell></TableRow>
                      )}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* Exceptions */}
          <TabsContent value='exceptions'>
            <Card>
              <CardHeader>
                <CardTitle className='flex items-center gap-2'>
                  <AlertTriangle className='h-4 w-4 text-red-500' />
                  Exception Shipments
                </CardTitle>
                <CardDescription>Shipments requiring manual review or escalation</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <Skeleton className='h-48 w-full' />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Order</TableHead>
                        <TableHead>Tracking</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Note</TableHead>
                        <TableHead>Escalated</TableHead>
                        <TableHead>Date</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data?.exceptions.map(row => (
                        <TableRow key={row.id}>
                          <TableCell>
                            <a href={`/orders`} className='text-primary hover:underline font-medium'>
                              {row.order?.order_number ?? row.order_id}
                            </a>
                            <div className='text-xs text-muted-foreground'>{row.order?.customer_name}</div>
                          </TableCell>
                          <TableCell className='font-mono text-xs'>{row.tracking_number ?? '—'}</TableCell>
                          <TableCell className='text-sm'>{row.courier_status_description ?? '—'}</TableCell>
                          <TableCell className='text-sm text-muted-foreground'>{row.exception_note ?? '—'}</TableCell>
                          <TableCell>
                            {row.exception_escalated_at ? (
                              <Badge variant='outline' className='bg-orange-100 text-orange-800 dark:bg-orange-900/30 text-xs'>
                                {fmtDate(row.exception_escalated_at)}
                              </Badge>
                            ) : (
                              <span className='text-muted-foreground text-xs'>No</span>
                            )}
                          </TableCell>
                          <TableCell className='text-muted-foreground text-xs'>{fmtDate(row.created_at)}</TableCell>
                        </TableRow>
                      ))}
                      {data?.exceptions.length === 0 && (
                        <TableRow><TableCell colSpan={6} className='text-center text-muted-foreground py-8'>No exceptions — great!</TableCell></TableRow>
                      )}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* Returns */}
          <TabsContent value='returns'>
            <Card>
              <CardHeader>
                <CardTitle className='flex items-center gap-2'>
                  <RotateCcw className='h-4 w-4 text-purple-500' />
                  Returned Shipments
                </CardTitle>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <Skeleton className='h-48 w-full' />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Order</TableHead>
                        <TableHead>Tracking</TableHead>
                        <TableHead>Return Reason</TableHead>
                        <TableHead>Returned At</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data?.returns.map(row => (
                        <TableRow key={row.id}>
                          <TableCell>
                            <span className='font-medium'>{row.order?.order_number ?? row.order_id}</span>
                            <div className='text-xs text-muted-foreground'>{row.order?.customer_name}</div>
                          </TableCell>
                          <TableCell className='font-mono text-xs'>{row.tracking_number ?? '—'}</TableCell>
                          <TableCell className='text-sm text-muted-foreground'>{row.cancel_reason || '—'}</TableCell>
                          <TableCell className='text-muted-foreground text-xs'>{fmtDate(row.cancelled_at)}</TableCell>
                        </TableRow>
                      ))}
                      {data?.returns.length === 0 && (
                        <TableRow><TableCell colSpan={4} className='text-center text-muted-foreground py-8'>No returns in this period</TableCell></TableRow>
                      )}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* COD */}
          <TabsContent value='cod'>
            <Card>
              <CardHeader>
                <CardTitle className='flex items-center gap-2'>
                  <Banknote className='h-4 w-4 text-amber-500' />
                  COD Remittances
                </CardTitle>
                <CardDescription>
                  {data?.cod.summary
                    ? `${fmt(data.cod.summary.total_collected)} collected · SAR ${Number(data.cod.summary.total_amount).toLocaleString()} total`
                    : 'Cash-on-delivery payments remitted by J&T'}
                </CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <Skeleton className='h-48 w-full' />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Order</TableHead>
                        <TableHead>Tracking</TableHead>
                        <TableHead className='text-right'>Order Total</TableHead>
                        <TableHead className='text-right'>COD Collected</TableHead>
                        <TableHead>Collected At</TableHead>
                        <TableHead>Payment</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data?.cod.transactions.map(row => (
                        <TableRow key={row.id}>
                          <TableCell>
                            <span className='font-medium'>{row.order_number}</span>
                            <div className='text-xs text-muted-foreground'>{row.customer_name}</div>
                          </TableCell>
                          <TableCell className='font-mono text-xs'>{row.tracking_number ?? '—'}</TableCell>
                          <TableCell className='text-right'>SAR {Number(row.total).toLocaleString()}</TableCell>
                          <TableCell className='text-right font-medium text-green-700 dark:text-green-400'>
                            SAR {Number(row.cod_collected_amount).toLocaleString()}
                          </TableCell>
                          <TableCell className='text-muted-foreground text-xs'>{fmtDate(row.cod_collected_at)}</TableCell>
                          <TableCell>
                            <Badge variant='outline' className={row.financial_status === 'paid'
                              ? 'bg-green-100 text-green-800 dark:bg-green-900/30 text-xs'
                              : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 text-xs'}>
                              {row.financial_status}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                      {data?.cod.transactions.length === 0 && (
                        <TableRow><TableCell colSpan={6} className='text-center text-muted-foreground py-8'>No COD remittances yet</TableCell></TableRow>
                      )}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </Main>
    </AuthenticatedLayout>
  )
}

// Local X icon
function X({ className }: { className?: string }) {
  return (
    <svg className={className} fill='none' viewBox='0 0 24 24' stroke='currentColor' strokeWidth={2}>
      <path strokeLinecap='round' strokeLinejoin='round' d='M6 18L18 6M6 6l12 12' />
    </svg>
  )
}
