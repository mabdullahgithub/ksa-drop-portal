import { useState } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { StatusBadge } from '@/features/partners/partner-shell'
import { formatDate } from '@/features/partners/types'
import type { LeadRow } from './types'

const STATUSES: LeadRow['status'][] = ['new', 'contacted', 'converted', 'lost']

/** Sellers partners sent in. The team works them over WhatsApp and marks the outcome. */
export function LeadsTab({ leads, onChanged }: { leads: LeadRow[]; onChanged: () => void }) {
  const [busy, setBusy] = useState<number | null>(null)

  const setStatus = async (lead: LeadRow, status: LeadRow['status']) => {
    setBusy(lead.id)
    try {
      await axios.patch(`/api/partners/leads/${lead.id}`, { status })
      toast.success('Lead updated.')
      onChanged()
    } catch {
      toast.error('Could not update the lead.')
    } finally {
      setBusy(null)
    }
  }

  const wa = (phone: string) => `https://wa.me/${phone.replace(/[^0-9]/g, '')}`

  return (
    <div className='rounded-md border'>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Seller</TableHead>
            <TableHead>Contact</TableHead>
            <TableHead>Store</TableHead>
            <TableHead>Volume</TableHead>
            <TableHead>Partner</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Received</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {leads.length === 0 ? (
            <TableRow><TableCell colSpan={7} className='py-10 text-center text-muted-foreground'>No leads yet.</TableCell></TableRow>
          ) : leads.map((l) => (
            <TableRow key={l.id}>
              <TableCell>
                <div className='font-medium'>{l.name}</div>
                <div className='text-xs text-muted-foreground'>{l.company ?? '—'}</div>
                {l.message && <div className='mt-1 max-w-xs truncate text-xs text-muted-foreground' title={l.message}>“{l.message}”</div>}
              </TableCell>
              <TableCell className='text-sm'>
                <a className='underline' href={wa(l.phone)} target='_blank' rel='noreferrer'>{l.phone}</a>
                {l.email && <div className='text-xs text-muted-foreground'>{l.email}</div>}
              </TableCell>
              <TableCell className='max-w-[160px] truncate text-sm'>{l.store_url ?? '—'}</TableCell>
              <TableCell className='text-sm'>{l.monthly_orders ?? '—'}</TableCell>
              <TableCell className='text-sm'>
                {l.partner ? <>{l.partner.display_name} <span className='font-mono text-xs text-muted-foreground'>{l.partner.code}</span></> : '—'}
              </TableCell>
              <TableCell>
                {l.client ? (
                  <StatusBadge status='converted' />
                ) : (
                  <select
                    className='h-8 rounded-md border border-input bg-transparent px-2 text-xs capitalize'
                    value={l.status}
                    disabled={busy === l.id}
                    onChange={(e) => setStatus(l, e.target.value as LeadRow['status'])}
                  >
                    {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                  </select>
                )}
              </TableCell>
              <TableCell className='whitespace-nowrap text-sm'>{formatDate(l.created_at)}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
      <p className='border-t px-3 py-2 text-xs text-muted-foreground'>
        When you open the seller's account, enter the partner's code in "Referred by partner" on the Create Client form. The lead is marked converted automatically once you pass its code.
      </p>
    </div>
  )
}
