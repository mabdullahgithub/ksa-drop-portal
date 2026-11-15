import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PartnerShell, PointsCard, ReferralBox, StatusBadge } from '@/features/partners/partner-shell'
import { formatDate, formatPoints, type PartnerSummary } from '@/features/partners/types'

type Props = {
  partner: PartnerSummary
  clients: {
    id: number
    name: string
    status: string
    referred_at: string | null
    activated_at: string | null
    delivered_count: number
    earning_until: string | null
  }[]
  leads: { id: number; name: string; company: string | null; status: string; created_at: string }[]
}

/** Who the partner brought in. Counts and status only; never a seller's money. */
export default function Referrals({ partner, clients, leads }: Props) {
  return (
    <PartnerShell title='Referrals' description='Sellers credited to you, and leads waiting for our team.' partner={partner}>
      <div className='mb-6 grid gap-4 lg:grid-cols-[3fr_2fr]'>
        <ReferralBox partner={partner} />
        <PointsCard partner={partner} />
      </div>

      <div className='grid gap-6 lg:grid-cols-[3fr_2fr]'>
        <Card>
          <CardHeader>
            <CardTitle>Referred sellers</CardTitle>
            <CardDescription>Accounts our team opened with your code. A seller becomes active at their first delivered order.</CardDescription>
          </CardHeader>
          <CardContent>
            {clients.length === 0 ? (
              <p className='py-8 text-center text-sm text-muted-foreground'>No sellers yet. Share your link to get started.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Seller</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Referred</TableHead>
                    <TableHead>Active since</TableHead>
                    <TableHead className='text-right'>Delivered</TableHead>
                    <TableHead>Earning until</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {clients.map((c) => (
                    <TableRow key={c.id}>
                      <TableCell className='font-medium'>{c.name}</TableCell>
                      <TableCell><StatusBadge status={c.activated_at ? 'active' : c.status === 'active' ? 'pending' : c.status} /></TableCell>
                      <TableCell>{formatDate(c.referred_at)}</TableCell>
                      <TableCell>{formatDate(c.activated_at)}</TableCell>
                      <TableCell className='text-right tabular-nums'>{formatPoints(c.delivered_count)}</TableCell>
                      <TableCell>{formatDate(c.earning_until)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Leads</CardTitle>
            <CardDescription>Sellers who filled the form on your referral page. Our team contacts each one.</CardDescription>
          </CardHeader>
          <CardContent>
            {leads.length === 0 ? (
              <p className='py-8 text-center text-sm text-muted-foreground'>No leads yet.</p>
            ) : (
              <ul className='divide-y'>
                {leads.map((l) => (
                  <li key={l.id} className='flex items-center justify-between gap-3 py-2.5 text-sm'>
                    <div className='min-w-0'>
                      <div className='truncate font-medium'>{l.name}</div>
                      <div className='truncate text-xs text-muted-foreground'>{l.company ?? '—'} · {formatDate(l.created_at)}</div>
                    </div>
                    <StatusBadge status={l.status} />
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </PartnerShell>
  )
}
