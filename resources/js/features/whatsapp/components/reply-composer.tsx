import { useState } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { formatDistanceToNowStrict } from 'date-fns'
import { Send, Loader2, Lock } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { usePermissions } from '@/hooks/use-permissions'
import type { ThreadMessage } from '../types'

/**
 * Agent reply box.
 *
 * Only free-form text, and only while WhatsApp's 24-hour customer service
 * window is open — the customer opens it by messaging us, and it closes 24h
 * after their last message. The closed state is spelled out rather than the box
 * simply being disabled, because "why can't I type?" is otherwise a support
 * question, and the answer ("call them instead") is actionable.
 */
export function ReplyComposer({
  orderId,
  windowOpen,
  windowExpiresAt,
  onSent,
}: {
  orderId: number
  windowOpen: boolean
  windowExpiresAt: string | null
  onSent: (message: ThreadMessage) => void
}) {
  const { can } = usePermissions()
  const [body, setBody] = useState('')
  const [sending, setSending] = useState(false)

  if (!can('edit orders')) return null

  const send = async () => {
    const text = body.trim()
    if (!text || sending) return

    setSending(true)
    try {
      const res = await axios.post(`/api/whatsapp/conversations/${orderId}/reply`, { body: text })
      setBody('')
      onSent(res.data.sent)
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Could not send the reply')
    } finally {
      setSending(false)
    }
  }

  if (!windowOpen) {
    return (
      <div className='flex items-start gap-2 border-t bg-muted/40 px-4 py-3 text-xs text-muted-foreground'>
        <Lock className='mt-0.5 h-3.5 w-3.5 shrink-0' />
        <p>
          The 24-hour reply window has closed. WhatsApp only accepts an approved template now —
          call the customer instead, or mark the call outcome to restart the flow.
        </p>
      </div>
    )
  }

  return (
    <div className='border-t bg-background px-3 py-2.5'>
      <div className='flex items-end gap-2'>
        <Textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          onKeyDown={(e) => {
            // Enter sends, Shift+Enter breaks the line — the convention in
            // every messaging client, so agents don't have to learn ours.
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault()
              send()
            }
          }}
          rows={1}
          placeholder='Type a reply…'
          className='max-h-32 min-h-9 resize-none py-2'
          disabled={sending}
        />
        <Button size='icon' className='h-9 w-9 shrink-0' onClick={send} disabled={sending || !body.trim()}>
          {sending ? <Loader2 className='h-4 w-4 animate-spin' /> : <Send className='h-4 w-4' />}
          <span className='sr-only'>Send reply</span>
        </Button>
      </div>
      {windowExpiresAt && (
        <p className='mt-1.5 text-[11px] text-muted-foreground'>
          Replies open for {formatDistanceToNowStrict(new Date(windowExpiresAt))} · Enter to send,
          Shift+Enter for a new line
        </p>
      )}
    </div>
  )
}
