import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { SignupRequest } from '@/hooks/useInventoryRequests'
import type { ClientCharges } from '@/types/client'

const FIELDS: { key: keyof ClientCharges; label: string }[] = [
  { key: 'delivery', label: 'Delivery (SAR)' },
  { key: 'return', label: 'Return (SAR)' },
  { key: 'cod', label: 'COD (SAR)' },
  { key: 'warehousing', label: 'Warehousing (SAR)' },
  { key: 'call_confirmation', label: 'Call confirmation (SAR)' },
  { key: 'vat', label: 'VAT (%)' },
  { key: 'other', label: 'Other (SAR)' },
]

type Props = {
  /** The signup being approved; null keeps the dialog closed. */
  signup: SignupRequest | null
  /** Without the permission to set charges the dialog only confirms. */
  canSetCharges: boolean
  saving: boolean
  onClose: () => void
  /** No charges means "skipped": whatever is not already set becomes 0. */
  onApprove: (charges?: ClientCharges) => void
}

export function SignupApproveDialog({ signup, canSetCharges, saving, onClose, onApprove }: Props) {
  const [values, setValues] = useState<Record<string, string>>({})

  // Opens on anything already set on the client page, so it is not lost.
  useEffect(() => {
    if (!signup) return
    setValues(
      Object.fromEntries(
        FIELDS.map(({ key }) => [key, signup.charges?.[key] ? String(signup.charges[key]) : '']),
      ),
    )
  }, [signup])

  const vatTooHigh = Number(values.vat || 0) > 100

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (saving || vatTooHigh) return

    onApprove(
      canSetCharges
        ? Object.fromEntries(FIELDS.map(({ key }) => [key, Number(values[key]) || 0]))
        : undefined,
    )
  }

  return (
    <Dialog open={!!signup} onOpenChange={(open) => !open && !saving && onClose()}>
      <DialogContent className='max-h-[90vh] max-w-lg overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>Approve {signup?.company_name}?</DialogTitle>
          <DialogDescription>
            {canSetCharges
              ? 'Set the charges for this client. Anything left blank is saved as 0, and you can change charges later on the client page.'
              : 'The account becomes active and the merchant is notified. You do not have permission to set charges, so any not already set will be 0.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className='space-y-4'>
          {canSetCharges && (
            <div className='grid grid-cols-1 gap-3 sm:grid-cols-2'>
              {FIELDS.map(({ key, label }) => (
                <div key={key} className='grid gap-1.5'>
                  <Label htmlFor={`signup-charge-${key}`}>{label}</Label>
                  <Input
                    id={`signup-charge-${key}`}
                    inputMode='decimal'
                    placeholder='0.00'
                    value={values[key] ?? ''}
                    disabled={saving}
                    onChange={(e) => {
                      const { value } = e.target
                      // Numbers and one decimal point only.
                      if (/^\d{0,6}(\.\d{0,2})?$/.test(value)) {
                        setValues((previous) => ({ ...previous, [key]: value }))
                      }
                    }}
                  />
                  {key === 'vat' && vatTooHigh && (
                    <p className='text-xs text-destructive'>VAT cannot be more than 100%.</p>
                  )}
                </div>
              ))}
            </div>
          )}

          <DialogFooter className='gap-2 sm:justify-between'>
            <Button type='button' variant='ghost' onClick={onClose} disabled={saving}>
              Cancel
            </Button>
            <div className='flex flex-col-reverse gap-2 sm:flex-row'>
              {canSetCharges && (
                <Button
                  type='button'
                  variant='outline'
                  onClick={() => onApprove()}
                  disabled={saving}
                >
                  Skip charges & approve
                </Button>
              )}
              <Button type='submit' disabled={saving || vatTooHigh}>
                {saving ? 'Approving…' : 'Approve'}
              </Button>
            </div>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
