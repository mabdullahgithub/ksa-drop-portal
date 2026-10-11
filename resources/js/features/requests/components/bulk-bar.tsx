import { useState } from 'react'
import { toast } from 'sonner'
import { Check, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type BulkResult, bulkApproveRequests, bulkRejectRequests } from '@/hooks/useInventoryRequests'
import { RejectDialog } from './request-parts'

type Props = {
  type: 'stock' | 'details'
  ids: number[]
  canApprove: boolean
  canReject: boolean
  /** What "approve" means for several at once, in a sentence. */
  approveNote: string
  onClear: () => void
  /** Something was decided: reload the queue and the counts. */
  onDone: () => void
}

/**
 * The bar that appears once rows are ticked: the same decision on all of them.
 * Each request still stands alone, so the ones that could not go through are
 * named afterwards and the rest are done.
 */
export function BulkBar({ type, ids, canApprove, canReject, approveNote, onClear, onDone }: Props) {
  const [confirmApprove, setConfirmApprove] = useState(false)
  const [rejecting, setRejecting] = useState(false)
  const [working, setWorking] = useState(false)

  if (ids.length === 0) return null

  const noun = `request${ids.length === 1 ? '' : 's'}`

  const report = (result: BulkResult, verb: string) => {
    if (result.done > 0) toast.success(`${result.done} ${verb}`)

    if (result.skipped.length > 0) {
      toast.warning(`${result.skipped.length} left as they were`, {
        description: result.skipped
          .slice(0, 4)
          .map((skipped) => `${skipped.product ?? `#${skipped.id}`}: ${skipped.message}`)
          .join('\n'),
        duration: 10000,
      })
    }

    onClear()
    onDone()
  }

  const approve = async () => {
    if (working) return
    setWorking(true)
    try {
      report(await bulkApproveRequests(type, ids), 'approved')
      setConfirmApprove(false)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to approve')
    } finally {
      setWorking(false)
    }
  }

  const reject = async (reason: string) => {
    try {
      report(await bulkRejectRequests(type, ids, reason), 'rejected')
      setRejecting(false)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to reject')
    }
  }

  return (
    <>
      <div className='flex flex-wrap items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 text-sm'>
        <span className='font-medium'>
          {ids.length} {noun} selected
        </span>
        <div className='ms-auto flex items-center gap-2'>
          {canApprove && (
            <Button size='sm' className='h-8 gap-1.5' onClick={() => setConfirmApprove(true)} disabled={working}>
              <Check className='h-4 w-4' />
              Approve
            </Button>
          )}
          {canReject && (
            <Button variant='destructive' size='sm' className='h-8 gap-1.5' onClick={() => setRejecting(true)} disabled={working}>
              <X className='h-4 w-4' />
              Reject
            </Button>
          )}
          <Button variant='ghost' size='sm' className='h-8' onClick={onClear} disabled={working}>
            Clear
          </Button>
        </div>
      </div>

      <ConfirmDialog
        open={confirmApprove}
        onOpenChange={setConfirmApprove}
        title={`Approve ${ids.length} ${noun}?`}
        desc={approveNote}
        confirmText='Approve'
        isLoading={working}
        handleConfirm={approve}
      />

      <RejectDialog
        open={rejecting}
        onOpenChange={setRejecting}
        title={`Reject ${ids.length} ${noun}`}
        description='One reason for all of them. Each client sees it on their own request.'
        onConfirm={reject}
      />
    </>
  )
}
