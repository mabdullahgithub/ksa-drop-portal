import { useState } from 'react'
import axios from 'axios'
import { MessageCircle, Plus } from 'lucide-react'
import { toast } from 'sonner'
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import type { RiderSupportContact } from '../data/types'

type Props = {
  support: RiderSupportContact | null
  canManage: boolean
  onChange: (support: RiderSupportContact | null) => void
}

/**
 * The WhatsApp number riders reach from the Profile screen of their app
 * when something goes wrong. Sits beside "Add rider"; managers click it to edit.
 */
export function RiderSupportButton({ support, canManage, onChange }: Props) {
  const [open, setOpen] = useState(false)

  if (!support && !canManage) return null

  const hint = support
    ? `Rider support on WhatsApp${support.name ? ` · ${support.name}` : ''}. Shown on every rider’s Profile screen.`
    : 'No support number yet. Riders have no help contact in the app.'

  return (
    <>
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant='outline'
            className={cn('gap-1.5 font-normal', !canManage && 'cursor-default hover:bg-transparent')}
            onClick={canManage ? () => setOpen(true) : undefined}
            aria-label={hint}
          >
            {support ? (
              <>
                <MessageCircle className='h-4 w-4 text-emerald-600 dark:text-emerald-400' />
                <span dir='ltr' className='tabular-nums'>
                  {support.whatsapp_local}
                </span>
              </>
            ) : (
              <>
                <Plus className='h-4 w-4 text-muted-foreground' />
                <span className='text-muted-foreground'>Support number</span>
              </>
            )}
          </Button>
        </TooltipTrigger>
        <TooltipContent>{hint}</TooltipContent>
      </Tooltip>

      {open && <SupportDialog support={support} onClose={() => setOpen(false)} onSaved={onChange} />}
    </>
  )
}

function SupportDialog({
  support,
  onClose,
  onSaved,
}: {
  support: RiderSupportContact | null
  onClose: () => void
  onSaved: (support: RiderSupportContact | null) => void
}) {
  const [whatsapp, setWhatsapp] = useState(support?.whatsapp_local ?? '')
  const [name, setName] = useState(support?.name ?? '')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const save = async (next: { whatsapp: string; name: string }) => {
    setSaving(true)
    setError(null)
    try {
      const { data } = await axios.put('/api/riders/support', next)
      toast.success(data.message)
      onSaved(data.support)
      onClose()
    } catch (err: any) {
      setError(err.response?.data?.errors?.whatsapp?.[0] ?? err.response?.data?.message ?? 'Could not save.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Rider support contact</DialogTitle>
          <DialogDescription>
            Riders see this WhatsApp number on their Profile screen and can message it when they have a problem with the
            app, a parcel or their cash.
          </DialogDescription>
        </DialogHeader>

        <form
          id='support-form'
          className='space-y-4'
          onSubmit={(event) => {
            event.preventDefault()
            save({ whatsapp: whatsapp.trim(), name: name.trim() })
          }}
        >
          <div className='space-y-1.5'>
            <Label htmlFor='support-whatsapp'>
              WhatsApp number<span className='text-destructive'>*</span>
            </Label>
            <Input
              id='support-whatsapp'
              value={whatsapp}
              onChange={(e) => setWhatsapp(e.target.value)}
              inputMode='tel'
              autoComplete='off'
              placeholder='05XXXXXXXX, 03XXXXXXXXX or +…'
              dir='ltr'
              autoFocus
            />
            {error ? (
              <p className='text-xs text-destructive'>{error}</p>
            ) : (
              <p className='text-xs text-muted-foreground'>Saudi or Pakistani mobile, or any number with its country code.</p>
            )}
          </div>
          <div className='space-y-1.5'>
            <Label htmlFor='support-name'>Shown as (optional)</Label>
            <Input
              id='support-name'
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder='e.g. KSA Express Support'
              maxLength={100}
            />
          </div>
        </form>

        <DialogFooter className='gap-2 sm:justify-between'>
          {support ? (
            <Button type='button' variant='ghost' className='text-destructive' disabled={saving} onClick={() => save({ whatsapp: '', name: '' })}>
              Remove contact
            </Button>
          ) : (
            <span />
          )}
          <div className='flex gap-2'>
            <Button type='button' variant='outline' onClick={onClose} disabled={saving}>
              Cancel
            </Button>
            <Button type='submit' form='support-form' disabled={saving || !whatsapp.trim()}>
              {saving ? 'Saving…' : 'Save'}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
