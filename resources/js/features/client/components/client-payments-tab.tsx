import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'
import { Plus, Trash2, ExternalLink, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { usePermissions } from '@/hooks/use-permissions'
import type { Client } from '@/types/client'

interface Payment {
  id: number
  amount: string
  message: string | null
  paid_at: string
  proof_url: string | null
  created_by: { id: number; name: string } | null
}

interface Props {
  client: Client
}

export function ClientPaymentsTab({ client }: Props) {
  const { can } = usePermissions()
  const [payments, setPayments] = useState<Payment[]>([])
  const [loading, setLoading] = useState(true)
  const [addOpen, setAddOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<Payment | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const amountRef   = useRef<HTMLInputElement>(null)
  const paidAtRef   = useRef<HTMLInputElement>(null)
  const messageRef  = useRef<HTMLTextAreaElement>(null)
  const proofRef    = useRef<HTMLInputElement>(null)

  const fetchPayments = useCallback(async () => {
    setLoading(true)
    try {
      const res = await fetch(`/api/clients/${client.id}/payments`)
      const json = await res.json()
      setPayments(json.data ?? [])
    } catch {
      toast.error('Failed to load payments')
    } finally {
      setLoading(false)
    }
  }, [client.id])

  useEffect(() => { fetchPayments() }, [fetchPayments])

  const handleAdd = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)

    const fd = new FormData()
    fd.append('amount',  amountRef.current?.value  ?? '')
    fd.append('paid_at', paidAtRef.current?.value  ?? '')
    fd.append('message', messageRef.current?.value ?? '')
    if (proofRef.current?.files?.[0]) {
      fd.append('proof', proofRef.current.files[0])
    }

    try {
      const res = await fetch(`/api/clients/${client.id}/payments`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' },
        body: fd,
      })

      if (!res.ok) {
        const err = await res.json()
        toast.error(err.message ?? 'Failed to record payment')
        return
      }

      toast.success('Payment recorded successfully')
      setAddOpen(false)
      fetchPayments()
    } catch {
      toast.error('Failed to record payment')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      const res = await fetch(`/api/clients/${client.id}/payments/${deleteTarget.id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
          'Content-Type': 'application/json',
        },
      })

      if (!res.ok) {
        toast.error('Failed to delete payment')
        return
      }

      toast.success('Payment deleted')
      setDeleteTarget(null)
      fetchPayments()
    } catch {
      toast.error('Failed to delete payment')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className='space-y-4'>
      <div className='flex items-center justify-between'>
        <div>
          <h3 className='text-sm font-semibold'>Payment History</h3>
          <p className='text-xs text-muted-foreground mt-0.5'>Manual transfers made to this client</p>
        </div>
        {can('edit client') && (
          <Dialog open={addOpen} onOpenChange={setAddOpen}>
            <DialogTrigger asChild>
              <Button size='sm' className='gap-1.5'>
                <Plus className='h-4 w-4' />
                Record Payment
              </Button>
            </DialogTrigger>
            <DialogContent className='max-w-md'>
              <DialogHeader>
                <DialogTitle>Record Payment</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleAdd} className='space-y-4'>
                <div className='grid grid-cols-2 gap-4'>
                  <div className='space-y-1.5'>
                    <Label htmlFor='amount'>Amount (SAR) <span className='text-destructive'>*</span></Label>
                    <Input
                      id='amount'
                      type='number'
                      step='0.01'
                      min='0.01'
                      placeholder='0.00'
                      ref={amountRef}
                      required
                    />
                  </div>
                  <div className='space-y-1.5'>
                    <Label htmlFor='paid_at'>Transfer Date <span className='text-destructive'>*</span></Label>
                    <Input
                      id='paid_at'
                      type='date'
                      ref={paidAtRef}
                      defaultValue={new Date().toISOString().split('T')[0]}
                      required
                    />
                  </div>
                </div>
                <div className='space-y-1.5'>
                  <Label htmlFor='message'>Message (optional)</Label>
                  <Textarea
                    id='message'
                    placeholder='e.g. Payment for June 2026 orders'
                    ref={messageRef}
                    rows={2}
                  />
                </div>
                <div className='space-y-1.5'>
                  <Label htmlFor='proof'>Proof of Payment (optional)</Label>
                  <div className='flex items-center gap-2'>
                    <Upload className='h-4 w-4 text-muted-foreground shrink-0' />
                    <Input
                      id='proof'
                      type='file'
                      accept='image/jpeg,image/png,image/jpg,application/pdf'
                      ref={proofRef}
                      className='cursor-pointer'
                    />
                  </div>
                  <p className='text-xs text-muted-foreground'>JPG, PNG or PDF — max 5 MB</p>
                </div>
                <DialogFooter>
                  <Button type='button' variant='outline' onClick={() => setAddOpen(false)} disabled={submitting}>
                    Cancel
                  </Button>
                  <Button type='submit' disabled={submitting}>
                    {submitting ? 'Saving...' : 'Save Payment'}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        )}
      </div>

      <Card>
        <CardContent className='pt-4'>
          {loading ? (
            <div className='space-y-3'>
              {[1, 2, 3].map((i) => (
                <div key={i} className='h-10 animate-pulse rounded bg-muted' />
              ))}
            </div>
          ) : payments.length === 0 ? (
            <div className='flex flex-col items-center justify-center py-10 text-center'>
              <p className='text-sm text-muted-foreground'>No payments recorded yet</p>
              <p className='text-xs text-muted-foreground mt-1'>Record a payment after transferring funds to this client</p>
            </div>
          ) : (
            <div className='overflow-x-auto'>
              <table className='w-full text-sm'>
                <thead>
                  <tr className='border-b text-left'>
                    <th className='pb-2 pr-4 font-medium text-muted-foreground'>Date</th>
                    <th className='pb-2 pr-4 font-medium text-muted-foreground'>Amount</th>
                    <th className='pb-2 pr-4 font-medium text-muted-foreground'>Message</th>
                    <th className='pb-2 pr-4 font-medium text-muted-foreground'>Proof</th>
                    <th className='pb-2 pr-4 font-medium text-muted-foreground'>By</th>
                    {can('edit client') && <th className='pb-2' />}
                  </tr>
                </thead>
                <tbody>
                  {payments.map((p) => (
                    <tr key={p.id} className='border-b last:border-0'>
                      <td className='py-2.5 pr-4 whitespace-nowrap text-muted-foreground'>
                        {new Date(p.paid_at).toLocaleDateString('en-SA', { year: 'numeric', month: 'short', day: 'numeric' })}
                      </td>
                      <td className='py-2.5 pr-4 font-semibold whitespace-nowrap text-green-600 dark:text-green-400'>
                        SAR {parseFloat(p.amount).toLocaleString()}
                      </td>
                      <td className='py-2.5 pr-4 text-muted-foreground max-w-[200px] truncate'>
                        {p.message || <span className='italic'>—</span>}
                      </td>
                      <td className='py-2.5 pr-4'>
                        {p.proof_url ? (
                          <a href={p.proof_url} target='_blank' rel='noopener noreferrer'>
                            <Button variant='outline' size='sm' className='h-7 gap-1.5 text-xs'>
                              <ExternalLink className='h-3 w-3' />
                              View
                            </Button>
                          </a>
                        ) : (
                          <span className='text-muted-foreground text-xs'>—</span>
                        )}
                      </td>
                      <td className='py-2.5 pr-4 text-xs text-muted-foreground'>
                        {p.created_by?.name ?? '—'}
                      </td>
                      {can('edit client') && (
                        <td className='py-2.5 text-right'>
                          <Button
                            variant='ghost'
                            size='icon'
                            className='h-7 w-7 text-muted-foreground hover:text-destructive'
                            onClick={() => setDeleteTarget(p)}
                          >
                            <Trash2 className='h-4 w-4' />
                          </Button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Payment</AlertDialogTitle>
            <AlertDialogDescription>
              Delete payment of <strong>SAR {deleteTarget ? parseFloat(deleteTarget.amount).toLocaleString() : ''}</strong> on{' '}
              {deleteTarget ? new Date(deleteTarget.paid_at).toLocaleDateString() : ''}? This cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={deleting}
              className='bg-destructive text-destructive-foreground hover:bg-destructive/90'
            >
              {deleting ? 'Deleting...' : 'Delete'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
