import { Users, UserCheck, Truck, PackageSearch } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { ClientStatistics } from '@/types/client'

interface ClientStatsProps {
  stats: ClientStatistics | null
  loading: boolean
}

export function ClientStats({ stats, loading }: ClientStatsProps) {
  if (loading || !stats) {
    return (
      <div className='grid gap-3 md:grid-cols-2 lg:grid-cols-4'>
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i} className='border-muted/50'>
            <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
              <div className='h-3 w-24 animate-pulse rounded bg-muted' />
            </CardHeader>
            <CardContent className='pb-0 -mt-3'>
              <div className='h-6 w-12 animate-pulse rounded bg-muted' />
            </CardContent>
          </Card>
        ))}
      </div>
    )
  }

  const cards = [
    {
      title: 'Total Clients',
      value: stats.total_clients,
      subtitle: `${stats.active_clients} active`,
      icon: Users,
    },
    {
      title: 'Dropshippers',
      value: stats.dropshippers_count,
      subtitle: 'Commission-based',
      icon: Truck,
    },
    {
      title: 'Fulfilment',
      value: stats.fulfilment_count,
      subtitle: 'Own stock in warehouse',
      icon: PackageSearch,
    },
    {
      title: 'Inactive / Suspended',
      value: stats.inactive_clients + stats.suspended_clients,
      subtitle: `${stats.suspended_clients} suspended`,
      icon: UserCheck,
    },
  ]

  return (
    <div className='grid gap-3 md:grid-cols-2 lg:grid-cols-4'>
      {cards.map((card) => (
        <Card key={card.title} className='border-muted/50'>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
            <CardTitle className='text-xs font-medium text-muted-foreground'>{card.title}</CardTitle>
            <card.icon className='h-5 w-5 text-muted-foreground' />
          </CardHeader>
          <CardContent className='pb-0 -mt-3'>
            <div className='text-xl font-bold'>{card.value}</div>
            <p className='text-[11px] text-muted-foreground mt-0'>{card.subtitle}</p>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
