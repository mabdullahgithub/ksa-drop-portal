import { useState } from 'react'
import axios from 'axios'
import { Link, router } from '@inertiajs/react'
import { toast } from 'sonner'
import { Settings } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { usePermissions } from '@/hooks/use-permissions'
import { useWhatsAppMessaging } from '@/hooks/use-whatsapp-messaging'
import { isWhatsAppLocked } from '../api'

/**
 * The messaging on/off switch.
 *
 * On: marking a call "No Answer" sends the confirmation, the 24h follow-up
 * goes out, replies confirm orders, and agents can reply here. Off: nothing is
 * sent, and the rest of the portal hides everything WhatsApp added. Flipping it
 * needs 'edit apps' (the WhatsApp connector's own permission); everyone else
 * just sees which way it is set.
 */
export function MessagingToggle({ onLocked }: { onLocked: () => void }) {
  const { can } = usePermissions()
  const enabled = useWhatsAppMessaging()
  const [saving, setSaving] = useState(false)
  const canEdit = can('edit apps')

  const toggle = async (next: boolean) => {
    setSaving(true)
    try {
      await axios.put('/api/whatsapp/messaging', { enabled: next })
      toast.success(next ? 'WhatsApp messaging is on' : 'WhatsApp messaging is off')
      // A shared prop, so every screen picks the new state up; the switch
      // stays disabled until it lands rather than flicking back for a moment.
      router.reload({ only: ['whatsappMessaging'], onFinish: () => setSaving(false) })
    } catch (err: any) {
      setSaving(false)
      if (isWhatsAppLocked(err)) {
        onLocked()
        return
      }
      toast.error(err.response?.data?.message || 'Could not change WhatsApp messaging')
    }
  }

  return (
    <div className='flex flex-wrap items-center gap-3 rounded-lg border bg-background px-4 py-3'>
      <Switch
        id='whatsapp-messaging'
        checked={enabled}
        onCheckedChange={toggle}
        disabled={!canEdit || saving}
        aria-describedby='whatsapp-messaging-hint'
      />
      <div className='min-w-0 flex-1'>
        <label htmlFor='whatsapp-messaging' className='text-sm font-medium'>
          Messaging {enabled ? 'on' : 'off'}
        </label>
        <p id='whatsapp-messaging-hint' className='text-xs text-muted-foreground'>
          {enabled
            ? 'Marking a call “No Answer” sends the confirmation message, with a follow-up after 24 hours.'
            : 'Nothing is sent to customers, and WhatsApp is hidden from Orders, the Dashboard and Apps.'}
          {!canEdit && ' Only someone who can edit apps can change this.'}
        </p>
      </div>
      {canEdit && (
        <Button variant='outline' size='sm' asChild>
          <Link href='/apps/whatsapp'>
            <Settings className='h-3.5 w-3.5' />
            Settings
          </Link>
        </Button>
      )}
    </div>
  )
}
