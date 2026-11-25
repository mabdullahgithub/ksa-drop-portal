import { useState } from 'react'
import { Link } from '@inertiajs/react'
import { toast } from 'sonner'
import { Check, CheckCircle, Clock, X, XCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { usePermissions } from '@/hooks/use-permissions'
import {
  type RequestFilters,
  type SignupRequest,
  approveSignupRequest,
  rejectSignupRequest,
  useRequestQueue,
} from '@/hooks/useInventoryRequests'
import { QueueRows, RejectDialog, RequestedAt, RequestsPager, RequestsToolbar } from './request-parts'

const STATUSES: Record<string, string> = {
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  all: 'All',
}

const SERVICES: Record<string, string> = {
  dropshipper: 'Dropshipping',
  fulfilment: 'Fulfilment',
}

const STATUS_STYLE = {
  pending: {
    label: 'Pending',
    icon: Clock,
    className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  },
  approved: {
    label: 'Approved',
    icon: CheckCircle,
    className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  },
  rejected: {
    label: 'Rejected',
    icon: XCircle,
    className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  },
}

type Props = {
  initialFilters: Partial<RequestFilters>
  /** Something was decided: the tab counts are stale. */
  onChanged: () => void
  /** The page locked again while open: back to the PIN prompt. */
  onLocked: () => void
}

/**
 * Merchants who signed up on the public page. Approving activates the account;
 * rejecting keeps it shut and emails the reason.
 */
export function SignupsPanel({ initialFilters, onChanged, onLocked }: Props) {
  const { can } = usePermissions()
  const canApprove = can('approve signup requests')
  const canReject = can('reject signup requests')
  const canOpenClient = can('view client details')

  // A client filter means nothing here: every row is a new client.
  const { rows, meta, loading, error, filters, updateFilters, refresh } =
    useRequestQueue<SignupRequest>('signups', { ...initialFilters, client_id: 'all' }, onLocked)
  const [approving, setApproving] = useState<SignupRequest | null>(null)
  const [rejecting, setRejecting] = useState<SignupRequest | null>(null)
  const [saving, setSaving] = useState(false)

  const waiting = filters.status === 'pending'

  const decided = () => {
    refresh()
    onChanged()
  }

  const approve = async () => {
    if (!approving || saving) return
    setSaving(true)
    try {
      await approveSignupRequest(approving.id)
      toast.success(`${approving.company_name} approved`)
      setApproving(null)
      decided()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to approve the signup')
    } finally {
      setSaving(false)
    }
  }

  const reject = async (reason: string) => {
    if (!rejecting) return
    try {
      await rejectSignupRequest(rejecting.id, reason)
      toast.success(`${rejecting.company_name} rejected`)
      setRejecting(null)
      decided()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to reject the signup')
    }
  }

  return (
    <div className='space-y-4'>
      <RequestsToolbar
        filters={filters}
        onChange={updateFilters}
        statuses={STATUSES}
        searchPlaceholder='Search company, name, email or phone…'
        onRefresh={refresh}
        loading={loading}
      />

      <div className='rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Business</TableHead>
              <TableHead>Contact</TableHead>
              <TableHead>City</TableHead>
              <TableHead>Service</TableHead>
              <TableHead>Submitted</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className='w-44' />
            </TableRow>
          </TableHeader>
          <TableBody>
            <QueueRows
              loading={loading}
              error={error}
              empty={rows.length === 0}
              columns={7}
              waiting={waiting}
              onRetry={refresh}
            >
              {rows.map((signup) => {
                const status = STATUS_STYLE[signup.status]

                return (
                  <TableRow key={signup.id}>
                    <TableCell>
                      <div className='font-medium'>
                        {canOpenClient ? (
                          <Link href={`/client/${signup.id}`} className='hover:underline'>
                            {signup.company_name}
                          </Link>
                        ) : (
                          signup.company_name
                        )}
                      </div>
                      <code className='mt-0.5 inline-block rounded bg-muted px-1.5 py-0.5 text-xs font-semibold'>
                        {signup.reference}
                      </code>
                    </TableCell>
                    <TableCell className='text-sm'>
                      <div>{signup.contact_person}</div>
                      <div className='text-xs text-muted-foreground'>{signup.email}</div>
                      <div className='text-xs text-muted-foreground'>{signup.phone}</div>
                    </TableCell>
                    <TableCell className='text-sm'>{signup.city || '—'}</TableCell>
                    <TableCell className='text-sm'>
                      {signup.client_types.map((type) => SERVICES[type] ?? type).join(', ')}
                    </TableCell>
                    <TableCell>
                      <RequestedAt value={signup.signed_up_at} waiting={signup.status === 'pending'} />
                    </TableCell>
                    <TableCell>
                      <Badge variant='outline' className={`text-xs ${status.className}`}>
                        <status.icon className='mr-1 h-3 w-3' />
                        {status.label}
                      </Badge>
                      {signup.reviewed_by && (
                        <div className='mt-1 text-xs text-muted-foreground'>by {signup.reviewed_by}</div>
                      )}
                      {signup.status === 'rejected' && signup.rejection_reason && (
                        <div className='mt-1 max-w-56 text-xs text-muted-foreground'>
                          {signup.rejection_reason}
                        </div>
                      )}
                    </TableCell>
                    <TableCell>
                      {signup.status === 'pending' && (
                        <div className='flex justify-end gap-2'>
                          {canApprove && (
                            <Button size='sm' className='h-8' onClick={() => setApproving(signup)}>
                              <Check className='mr-1.5 h-4 w-4' />
                              Approve
                            </Button>
                          )}
                          {canReject && (
                            <Button
                              variant='outline'
                              size='sm'
                              className='h-8'
                              onClick={() => setRejecting(signup)}
                            >
                              <X className='mr-1.5 h-4 w-4' />
                              Reject
                            </Button>
                          )}
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                )
              })}
            </QueueRows>
          </TableBody>
        </Table>
      </div>

      <RequestsPager meta={meta} onPage={(page) => updateFilters({ page })} />

      <Dialog open={!!approving} onOpenChange={(open) => !open && !saving && setApproving(null)}>
        <DialogContent className='max-w-md'>
          <DialogHeader>
            <DialogTitle>Approve {approving?.company_name}?</DialogTitle>
            <DialogDescription>
              The account becomes active and the merchant is notified. Check their charges are set on
              the client page first.
            </DialogDescription>
          </DialogHeader>
          {approving && canOpenClient && (
            <Link href={`/client/${approving.id}`} className='text-sm underline underline-offset-4'>
              Open client page
            </Link>
          )}
          <DialogFooter>
            <Button variant='outline' onClick={() => setApproving(null)} disabled={saving}>
              Cancel
            </Button>
            <Button onClick={approve} disabled={saving}>
              {saving ? 'Approving…' : 'Approve'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <RejectDialog
        open={!!rejecting}
        onOpenChange={(open) => !open && setRejecting(null)}
        title='Reject signup request'
        description={
          <>
            {rejecting?.company_name} will not be able to sign in. The reason is included in the email
            to them.
          </>
        }
        onConfirm={reject}
      />
    </div>
  )
}
