import { useState } from 'react'
import axios from 'axios'
import { FileText } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { StatusBadge } from '@/features/partners/partner-shell'
import { formatDate, formatPoints } from '@/features/partners/types'
import type { PayoutRow } from './types'

const errorMessage = (e: unknown) =>
  (axios.isAxiosError(e) && (e.response?.data?.message as string)) || 'Something went wrong.'

/**
 * The payout queue. Approve takes the points off the partner's balance; Pay
 * records the transfer a person made, with proof; Reject gives points back
 * if they were already taken.
 */
export function PayoutsTab({ payouts, canHandle, onChanged }: { payouts: PayoutRow[]; canHandle: boolean; onChanged: () => void }) {
  const [busy, setBusy] = useState(false)
  const [paying, setPaying] = useState<PayoutRow | null>(null)
  const [rejecting, setRejecting] = useState<PayoutRow | null>(null)
  const [pay, setPay] = useState({ amount: '', currency: 'SAR', note: '', proof: null as File | null })
  const [reason, setReason] = useState('')

  const run = async (fn: () => Promise<unknown>, done?: () => void) => {
    setBusy(true)
    try {
      const res = (await fn()) as { data?: { message?: string } }
      if (res?.data?.message) toast.success(res.data.message)
      done?.()
      onChanged()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  const submitPay = () => {
    if (!paying) return
    const fd = new FormData()
    fd.append('amount', pay.amount)
    fd.append('currency', pay.currency)
    if (pay.note) fd.append('note', pay.note)
    if (pay.proof) fd.append('proof', pay.proof)
    run(() => axios.post(`/api/partners/payouts/${paying.id}/pay`, fd), () => { setPaying(null); setPay({ amount: '', currency: 'SAR', note: '', proof: null }) })
  }

  const method = (r: PayoutRow) => {
    const m = r.payout_method
    if (!m?.type) return '—'
    return m.type === 'bank' ? `${m.bank_name ?? 'Bank'} · ${m.holder_name ?? ''} · ${m.iban ?? ''}` : m.details ?? 'Other'
  }

  return (
    <>
      <div className='rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Partner</TableHead>
              <TableHead className='text-right'>Points</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Pay to</TableHead>
              <TableHead>Paid</TableHead>
              <TableHead>Requested</TableHead>
              <TableHead className='text-right'>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {payouts.length === 0 ? (
              <TableRow><TableCell colSpan={7} className='py-10 text-center text-muted-foreground'>No payout requests.</TableCell></TableRow>
            ) : payouts.map((r) => (
              <TableRow key={r.id}>
                <TableCell>
                  <div className='font-medium'>{r.partner?.display_name}</div>
                  <div className='text-xs text-muted-foreground'><span className='font-mono'>{r.partner?.code}</span> · balance {formatPoints(r.partner?.points_balance ?? 0)}</div>
                  {r.partner_note && <div className='mt-1 max-w-xs truncate text-xs text-muted-foreground' title={r.partner_note}>“{r.partner_note}”</div>}
                </TableCell>
                <TableCell className='text-right tabular-nums font-semibold'>{formatPoints(r.points)}</TableCell>
                <TableCell>
                  <StatusBadge status={r.status} />
                  {r.admin_note && <div className='mt-1 max-w-[180px] truncate text-xs text-muted-foreground' title={r.admin_note}>{r.admin_note}</div>}
                </TableCell>
                <TableCell className='max-w-[220px] text-xs'>{method(r)}</TableCell>
                <TableCell className='whitespace-nowrap text-sm'>
                  {r.status === 'paid' ? (
                    <>
                      {Number(r.amount_paid).toFixed(2)} {r.currency} · {formatDate(r.paid_at)}
                      {r.has_proof && <a className='ms-2 inline-flex items-center gap-1 underline' href={`/api/partners/payouts/${r.id}/proof`} target='_blank' rel='noreferrer'><FileText className='h-3.5 w-3.5' />proof</a>}
                      {r.handled_by && <div className='text-xs text-muted-foreground'>by {r.handled_by}</div>}
                    </>
                  ) : '—'}
                </TableCell>
                <TableCell className='whitespace-nowrap text-sm'>{formatDate(r.created_at)}</TableCell>
                <TableCell className='text-right'>
                  {canHandle && (
                    <div className='flex justify-end gap-1'>
                      {r.status === 'requested' && (
                        <Button size='sm' disabled={busy} onClick={() => run(() => axios.post(`/api/partners/payouts/${r.id}/approve`))}>Approve</Button>
                      )}
                      {r.status === 'approved' && (
                        <Button size='sm' disabled={busy} onClick={() => { setPaying(r); setPay({ amount: String(r.sar_value), currency: 'SAR', note: '', proof: null }) }}>Mark paid</Button>
                      )}
                      {(r.status === 'requested' || r.status === 'approved') && (
                        <Button size='sm' variant='outline' disabled={busy} onClick={() => { setRejecting(r); setReason('') }}>Reject</Button>
                      )}
                    </div>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <Dialog open={!!paying} onOpenChange={(o) => !o && setPaying(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Record payout #{paying?.id} as paid</DialogTitle></DialogHeader>
          <div className='space-y-3'>
            <p className='text-sm text-muted-foreground'>{paying && method(paying)}</p>
            <div className='grid grid-cols-[1fr_100px] gap-3'>
              <div className='grid gap-1.5'><Label>Amount transferred</Label><Input type='number' step='0.01' value={pay.amount} onChange={(e) => setPay({ ...pay, amount: e.target.value })} /></div>
              <div className='grid gap-1.5'><Label>Currency</Label><Input maxLength={3} value={pay.currency} onChange={(e) => setPay({ ...pay, currency: e.target.value.toUpperCase() })} /></div>
            </div>
            <div className='grid gap-1.5'><Label>Proof (image or PDF)</Label><Input type='file' accept='image/*,.pdf' onChange={(e) => setPay({ ...pay, proof: e.target.files?.[0] ?? null })} /></div>
            <div className='grid gap-1.5'><Label>Note to partner</Label><Textarea rows={2} value={pay.note} onChange={(e) => setPay({ ...pay, note: e.target.value })} /></div>
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setPaying(null)}>Cancel</Button>
            <Button disabled={busy || !pay.amount || pay.currency.length !== 3} onClick={submitPay}>Mark paid & email partner</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!rejecting} onOpenChange={(o) => !o && setRejecting(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Reject payout #{rejecting?.id}</DialogTitle></DialogHeader>
          <p className='text-sm text-muted-foreground'>
            {rejecting?.status === 'approved' ? 'The points already deducted are returned to the partner.' : 'The points stay on the partner\'s balance.'}
          </p>
          <div className='grid gap-1.5'><Label>Reason (shown to the partner)</Label><Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} /></div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setRejecting(null)}>Cancel</Button>
            <Button variant='destructive' disabled={busy || reason.trim().length < 3} onClick={() => rejecting && run(() => axios.post(`/api/partners/payouts/${rejecting.id}/reject`, { reason }), () => setRejecting(null))}>Reject</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
