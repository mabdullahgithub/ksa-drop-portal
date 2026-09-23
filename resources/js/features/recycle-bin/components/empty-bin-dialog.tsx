import { useState } from 'react'
import { AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'

const CONFIRM_WORD = 'DELETE'

type EmptyBinDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Total in this tab, unfiltered — an active search must not make this look smaller than it is. */
  count: number
  entityLabel: string
  onConfirm: () => Promise<void>
}

export function EmptyBinDialog({ open, onOpenChange, count, entityLabel, onConfirm }: EmptyBinDialogProps) {
  const [value, setValue] = useState('')
  const [working, setWorking] = useState(false)

  const handleConfirm = async () => {
    if (value.trim() !== CONFIRM_WORD) {
      toast.error(`Please type "${CONFIRM_WORD}" to confirm.`)
      return
    }

    setWorking(true)
    try {
      await onConfirm()
      setValue('')
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Failed to empty the bin')
    } finally {
      setWorking(false)
    }
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={(next) => {
        if (!next) setValue('')
        onOpenChange(next)
      }}
      disabled={value.trim() !== CONFIRM_WORD || working}
      isLoading={working}
      destructive
      title={
        <span className='text-destructive'>
          <AlertTriangle className='me-1 inline-block stroke-destructive' size={18} />
          Empty {entityLabel} bin
        </span>
      }
      desc={
        <div className='space-y-4'>
          <p>
            This permanently deletes all <strong>{count}</strong> deleted{' '}
            {entityLabel.toLowerCase()}
            {count === 1 ? '' : 's'}, including any not shown by the current search. It cannot be
            undone.
          </p>

          <Label className='flex flex-col items-start gap-1.5'>
            <span>Type "{CONFIRM_WORD}" to confirm:</span>
            <Input
              value={value}
              onChange={(event) => setValue(event.target.value)}
              placeholder={`Type "${CONFIRM_WORD}" to confirm.`}
            />
          </Label>

          <Alert variant='destructive'>
            <AlertTitle>This cannot be undone.</AlertTitle>
            <AlertDescription>
              Attached records go too — orders take their items, shipments and invoices; clients
              take their products, payments and store connection.
            </AlertDescription>
          </Alert>
        </div>
      }
      confirmText='Empty bin'
      handleConfirm={handleConfirm}
    />
  )
}
