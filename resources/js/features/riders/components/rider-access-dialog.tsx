import { useState } from 'react'
import axios from 'axios'
import { format } from 'date-fns'
import { Check, Copy, KeyRound, Link2, MessageCircle } from 'lucide-react'
import { QRCodeSVG } from 'qrcode.react'
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
import type { RiderRow } from '../data/types'

export type AccessKind = 'link' | 'pin'

type LinkResult = { url: string; expires_at: string; whatsapp_url: string; message_text: string; rider: RiderRow }
type PinResult = { pin: string; phone: string; app_url: string; whatsapp_url: string; message_text: string; rider: RiderRow }

type Props = {
  kind: AccessKind
  rider: RiderRow
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: (rider: RiderRow) => void
}

/**
 * Get a rider into the app: a one-time install link (the normal way), or a
 * PIN for phone + PIN sign-in (the fallback). Nothing is generated until the
 * admin asks — a new link voids the old one, a new PIN replaces the old PIN.
 */
export function RiderAccessDialog({ kind, rider, open, onOpenChange, onChanged }: Props) {
  const [loading, setLoading] = useState(false)
  const [link, setLink] = useState<LinkResult | null>(null)
  const [pin, setPin] = useState<PinResult | null>(null)
  const [copied, setCopied] = useState<string | null>(null)

  const generate = async () => {
    setLoading(true)
    try {
      if (kind === 'link') {
        const { data } = await axios.post<LinkResult>(`/api/riders/${rider.id}/activation-link`)
        setLink(data)
        onChanged(data.rider)
      } else {
        const { data } = await axios.post<PinResult>(`/api/riders/${rider.id}/pin`)
        setPin(data)
        onChanged(data.rider)
      }
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Something went wrong.')
    } finally {
      setLoading(false)
    }
  }

  const copy = async (text: string, what: string) => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(what)
      setTimeout(() => setCopied(null), 1500)
    } catch {
      toast.error('Could not copy. Select the text and copy it manually.')
    }
  }

  const result = link ?? pin

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>{kind === 'link' ? 'Send app link' : 'Give a PIN'}</DialogTitle>
          <DialogDescription>
            {kind === 'link'
              ? `${rider.name} opens this link on their phone, installs the app, and is signed in automatically.`
              : `For when the link doesn't work: ${rider.name} signs in with their phone number and this PIN.`}
          </DialogDescription>
        </DialogHeader>

        {!result && (
          <div className='space-y-3 text-sm'>
            {kind === 'link' ? (
              <ul className='list-disc space-y-1 ps-5 text-muted-foreground'>
                <li>Works once, on one phone, for 7 days.</li>
                {rider.has_pending_link && <li className='text-amber-700 dark:text-amber-400'>The link sent earlier stops working.</li>}
                {rider.device && <li className='text-amber-700 dark:text-amber-400'>Opening it signs the rider’s current phone out.</li>}
              </ul>
            ) : (
              <ul className='list-disc space-y-1 ps-5 text-muted-foreground'>
                <li>6 digits, shown only once. Only a scrambled copy is kept.</li>
                {rider.has_pin && <li className='text-amber-700 dark:text-amber-400'>This replaces the rider’s current PIN.</li>}
                {rider.pin_locked && <li>It also unlocks PIN sign-in after too many wrong tries.</li>}
                {rider.device && <li className='text-amber-700 dark:text-amber-400'>Signing in with it signs the rider’s current phone out.</li>}
              </ul>
            )}
          </div>
        )}

        {link && (
          <div className='space-y-4'>
            <div className='flex justify-center rounded-lg border bg-white p-4'>
              <QRCodeSVG value={link.url} size={176} />
            </div>
            <div className='flex items-center gap-2 rounded-md border bg-muted/40 p-2'>
              <Link2 className='h-4 w-4 shrink-0 text-muted-foreground' />
              <span className='min-w-0 flex-1 truncate font-mono text-xs' dir='ltr'>{link.url}</span>
              <Button size='sm' variant='ghost' onClick={() => copy(link.url, 'url')}>
                {copied === 'url' ? <Check className='h-4 w-4' /> : <Copy className='h-4 w-4' />}
              </Button>
            </div>
            <p className='text-xs text-muted-foreground'>
              Expires {format(new Date(link.expires_at), 'd MMM yyyy, HH:mm')}. The rider can also scan the QR code with their phone camera.
            </p>
          </div>
        )}

        {pin && (
          <div className='space-y-3'>
            <div className='rounded-lg border p-4 text-center'>
              <p className='text-xs text-muted-foreground'>Phone</p>
              <p className='font-mono text-lg' dir='ltr'>{pin.phone}</p>
              <p className='mt-3 text-xs text-muted-foreground'>PIN</p>
              <p className='font-mono text-4xl font-bold tracking-[0.3em]' dir='ltr'>{pin.pin}</p>
            </div>
            <p className='text-xs text-muted-foreground'>
              The rider opens <span className='font-mono' dir='ltr'>{pin.app_url}</span> and signs in. This PIN won’t be shown again.
            </p>
          </div>
        )}

        <DialogFooter className='gap-2 sm:gap-2'>
          {!result ? (
            <>
              <Button variant='outline' onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button onClick={generate} disabled={loading}>
                {kind === 'link' ? <Link2 className='me-1 h-4 w-4' /> : <KeyRound className='me-1 h-4 w-4' />}
                {loading ? 'Generating…' : kind === 'link' ? 'Create link' : 'Generate PIN'}
              </Button>
            </>
          ) : (
            <>
              <Button variant='outline' onClick={() => copy(result.message_text, 'message')}>
                {copied === 'message' ? <Check className='me-1 h-4 w-4' /> : <Copy className='me-1 h-4 w-4' />}
                Copy message
              </Button>
              <Button asChild className='bg-emerald-600 text-white hover:bg-emerald-700'>
                <a href={result.whatsapp_url} target='_blank' rel='noopener noreferrer'>
                  <MessageCircle className='me-1 h-4 w-4' />
                  Send on WhatsApp
                </a>
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
