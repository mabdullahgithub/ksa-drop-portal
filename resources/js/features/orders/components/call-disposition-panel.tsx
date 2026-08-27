import { useState, useEffect, useCallback } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { format } from 'date-fns'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { PhoneCall, MessageCircle, Check, CheckCheck, AlertTriangle, Loader2 } from 'lucide-react'
import { usePermissions } from '@/hooks/use-permissions'
import type { CallStatus, Order, WhatsAppMessage } from '@/types/order'
import { CALL_STATUS_OPTIONS, WHATSAPP_STATUS_META } from '../data/call-status'

interface Props {
  order: Order
  onSaved?: () => void
}

/**
 * Call outcome + the WhatsApp conversation it triggers.
 *
 * Setting "No Answer" here is the entry point to the whole confirmation flow:
 * the server-side observer picks up the change and sends the first WhatsApp
 * message, so there is deliberately no separate "send WhatsApp" button — one
 * action, one meaning.
 */
export function CallDispositionPanel({ order, onSaved }: Props) {
  const { can } = usePermissions()
  const editable = can('edit orders')

  const [callStatus, setCallStatus] = useState<CallStatus>(order.call_status ?? 'not_called')
  const [notes, setNotes] = useState(order.call_notes ?? '')
  const [saving, setSaving] = useState(false)
  const [messages, setMessages] = useState<WhatsAppMessage[]>([])
  const [loadingMessages, setLoadingMessages] = useState(false)

  useEffect(() => {
    setCallStatus(order.call_status ?? 'not_called')
    setNotes(order.call_notes ?? '')
  }, [order.id, order.call_status, order.call_notes])

  const loadMessages = useCallback(async () => {
    if (!order.whatsapp_status) return
    setLoadingMessages(true)
    try {
      const res = await axios.get(`/api/orders/${order.id}/whatsapp-messages`)
      setMessages(res.data.messages ?? [])
    } catch {
      // A failed history fetch shouldn't block the call controls above it.
    } finally {
      setLoadingMessages(false)
    }
  }, [order.id, order.whatsapp_status])

  useEffect(() => {
    loadMessages()
  }, [loadMessages])

  const save = async () => {
    setSaving(true)
    try {
      await axios.post(`/api/orders/${order.id}/call-status`, {
        call_status: callStatus,
        call_notes: notes || null,
      })
      toast.success(
        callStatus === 'no_answer'
          ? 'Marked as no answer — WhatsApp confirmation queued'
          : 'Call status updated'
      )
      onSaved?.()
      loadMessages()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to update call status')
    } finally {
      setSaving(false)
    }
  }

  const dirty = callStatus !== (order.call_status ?? 'not_called') || notes !== (order.call_notes ?? '')
  const waMeta = order.whatsapp_status ? WHATSAPP_STATUS_META[order.whatsapp_status] : null

  return (
    <div className='space-y-4'>
      <h3 className='font-semibold flex items-center gap-2'>
        <PhoneCall className='h-4 w-4' />
        Confirmation Call
      </h3>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-1'>
          <Label className='text-xs text-muted-foreground'>Call outcome</Label>
          <Select
            value={callStatus}
            onValueChange={(v) => setCallStatus(v as CallStatus)}
            disabled={!editable}
          >
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              {CALL_STATUS_OPTIONS.map((o) => (
                <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className='space-y-1'>
          <Label className='text-xs text-muted-foreground'>Attempts</Label>
          <div className='flex h-9 items-center gap-2 text-sm'>
            <span className='font-medium'>{order.call_attempts ?? 0}</span>
            {order.last_called_at && (
              <span className='text-xs text-muted-foreground'>
                last {format(new Date(order.last_called_at), 'dd MMM yyyy HH:mm')}
              </span>
            )}
          </div>
        </div>
      </div>

      <div className='space-y-1'>
        <Label className='text-xs text-muted-foreground'>Call notes</Label>
        <Textarea
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          rows={2}
          disabled={!editable}
          placeholder='What the customer said, callback time, etc.'
        />
      </div>

      {editable && (
        <div className='flex items-center gap-3'>
          <Button size='sm' onClick={save} disabled={saving || !dirty}>
            {saving ? 'Saving...' : 'Save call outcome'}
          </Button>
          {callStatus === 'no_answer' && !order.whatsapp_status && (
            <span className='text-xs text-muted-foreground'>
              Saving will send a WhatsApp confirmation message.
            </span>
          )}
        </div>
      )}

      {order.whatsapp_status && (
        <>
          <div className='flex flex-wrap items-center gap-3 rounded-md border p-3'>
            <MessageCircle className='h-4 w-4 text-muted-foreground' />
            <Badge variant='outline' className={`text-xs ${waMeta?.className ?? ''}`}>
              {waMeta?.label ?? order.whatsapp_status}
            </Badge>

            {order.whatsapp_sent_at && (
              <span className='text-xs text-muted-foreground'>
                Sent {format(new Date(order.whatsapp_sent_at), 'dd MMM HH:mm')}
              </span>
            )}
            {order.whatsapp_followup_sent_at && (
              <span className='text-xs text-muted-foreground'>
                · Follow-up {format(new Date(order.whatsapp_followup_sent_at), 'dd MMM HH:mm')}
              </span>
            )}

            {/* Delivery receipts. A missing "read" is not evidence the customer
                ignored us — WhatsApp only reports it when they have read
                receipts switched on — so it is never rendered as "unread". */}
            {order.whatsapp_read_at ? (
              <span className='flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400'>
                <CheckCheck className='h-3.5 w-3.5' />
                Read {format(new Date(order.whatsapp_read_at), 'dd MMM HH:mm')}
              </span>
            ) : order.whatsapp_delivered_at ? (
              <span
                className='flex items-center gap-1 text-xs text-muted-foreground'
                title='Delivered. A read receipt only arrives if the customer has them enabled.'
              >
                <Check className='h-3.5 w-3.5' />
                Delivered {format(new Date(order.whatsapp_delivered_at), 'dd MMM HH:mm')}
              </span>
            ) : null}

            {order.whatsapp_status === 'failed' && (
              <span className='flex items-center gap-1 text-xs text-red-600 dark:text-red-400'>
                <AlertTriangle className='h-3.5 w-3.5' />
                Not deliverable — number may not be on WhatsApp
              </span>
            )}
          </div>

          {loadingMessages ? (
            <div className='flex items-center gap-2 text-xs text-muted-foreground'>
              <Loader2 className='h-3.5 w-3.5 animate-spin' /> Loading conversation...
            </div>
          ) : messages.length > 0 ? (
            <div className='space-y-2'>
              {messages.map((m) => (
                <div
                  key={m.id}
                  className={`max-w-[85%] rounded-lg px-3 py-2 text-sm ${
                    m.direction === 'outbound'
                      ? 'ms-auto bg-primary/10'
                      : 'me-auto bg-muted'
                  }`}
                >
                  <div className='whitespace-pre-wrap break-words'>{m.body || '—'}</div>
                  <div className='mt-1 flex items-center gap-2 text-[10px] text-muted-foreground'>
                    <span>{format(new Date(m.created_at), 'dd MMM HH:mm')}</span>
                    {m.direction === 'outbound' && m.status && <span>· {m.status}</span>}
                    {m.error_message && (
                      <span className='text-red-600 dark:text-red-400'>· {m.error_message}</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : null}
        </>
      )}
    </div>
  )
}
