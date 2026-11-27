import { useState } from 'react'
import {
  CheckCircle2,
  FileSpreadsheet,
  FileText,
  Lock,
  PauseCircle,
  PencilLine,
  RotateCcw,
  XCircle,
  Download,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'
import { CATEGORY_LABELS, PRIORITY_META, STATUS_META } from '../../constants'
import type { ChatAttachment, ChatMessage, ChatSide, ChatStatus } from '../../types'
import { VoicePlayer } from './voice-player'

export function StatusBadge({ status, side, className }: { status: ChatStatus; side: ChatSide; className?: string }) {
  const meta = STATUS_META[status]
  return (
    <span className={cn('inline-flex items-center gap-1.5 text-xs text-muted-foreground', className)}>
      <span className={cn('size-1.5 rounded-full', meta.dot)} />
      {side === 'team' ? meta.label : meta.clientLabel}
    </span>
  )
}

export function StatusDot({ status, className }: { status: ChatStatus; className?: string }) {
  return <span className={cn('inline-block size-2 shrink-0 rounded-full', STATUS_META[status].dot, className)} />
}

/** What a timeline event says, from where the reader stands. */
export function describeEvent(message: ChatMessage, side: ChatSide): { text: string; icon: React.ElementType } {
  const meta = message.meta ?? {}
  const by = meta.by as string | undefined
  const status = (s: string) => (side === 'team' ? STATUS_META[s as ChatStatus]?.label : STATUS_META[s as ChatStatus]?.clientLabel) ?? s

  switch (meta.event) {
    case 'details': {
      const changes = Object.entries((meta.changes ?? {}) as Record<string, [unknown, unknown]>).map(([field, [, to]]) => {
        if (field === 'priority') return `priority to ${PRIORITY_META[to as keyof typeof PRIORITY_META]?.label ?? to}`
        if (field === 'category') return `category to ${CATEGORY_LABELS[to as keyof typeof CATEGORY_LABELS] ?? to}`
        if (field === 'order_id') return to ? `linked order ${to}` : 'unlinked the order'
        return `${field} to “${to}”`
      })
      return { text: `${by ?? 'Someone'} changed ${changes.join(', ')}`, icon: PencilLine }
    }
    case 'status': {
      const to = meta.to as ChatStatus
      if (meta.auto) return { text: 'Closed automatically after a week with no reply', icon: XCircle }
      const who = message.sender_type === 'client' ? (side === 'client' ? 'You' : 'The client') : (by ?? 'Support')
      const icon = to === 'resolved' ? CheckCircle2 : to === 'closed' ? XCircle : to === 'on_hold' ? PauseCircle : RotateCcw
      if (to === 'open' && (meta.from === 'resolved' || meta.from === 'closed')) return { text: `${who} reopened the chat`, icon }
      return { text: `${who} marked this chat ${status(to).toLowerCase()}`, icon }
    }
    default:
      return { text: 'Updated', icon: PencilLine }
  }
}

export function EventLine({ message, side }: { message: ChatMessage; side: ChatSide }) {
  const { text, icon: Icon } = describeEvent(message, side)
  return (
    <div className='flex items-center justify-center gap-1.5 py-1.5 text-center text-[11px] text-muted-foreground'>
      {message.internal && side === 'team' && <Lock className='size-3 shrink-0 opacity-60' aria-label='Only the team sees this' />}
      <Icon className='size-3 shrink-0' />
      <span>{text}</span>
    </div>
  )
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

/** Images as thumbnails that open full size; other files as a download tile. */
export function Attachments({ files, onDark }: { files: ChatAttachment[]; onDark?: boolean }) {
  const [preview, setPreview] = useState<ChatAttachment | null>(null)
  const voices = files.filter((f) => f.is_voice)
  const images = files.filter((f) => f.is_image)
  const others = files.filter((f) => !f.is_image && !f.is_voice)

  return (
    <div className='flex flex-col gap-1.5'>
      {voices.map((voice) => (
        <VoicePlayer key={voice.id} src={voice.url} duration={voice.duration ?? 0} onDark={onDark} />
      ))}
      {images.length > 0 && (
        <div className={cn('grid max-w-52 gap-1', images.length > 1 ? 'grid-cols-2' : 'grid-cols-1')}>
          {images.map((image) => (
            <button
              key={image.id}
              type='button'
              onClick={() => image.url && setPreview(image)}
              className='overflow-hidden rounded-lg bg-black/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
            >
              {image.url ? (
                <img src={image.url} alt={image.name} loading='lazy' className='max-h-36 w-full object-cover' />
              ) : (
                <div className='h-24 w-full animate-pulse bg-muted' />
              )}
            </button>
          ))}
        </div>
      )}
      {others.map((file) => {
        const Icon = /sheet|excel|csv/.test(file.mime) ? FileSpreadsheet : FileText
        return (
          <a
            key={file.id}
            href={file.url || undefined}
            target='_blank'
            rel='noopener noreferrer'
            className={cn(
              'flex min-w-0 items-center gap-2 rounded-lg border px-2.5 py-2 text-start transition-colors',
              onDark ? 'border-white/25 bg-white/10 hover:bg-white/15' : 'bg-background hover:bg-muted'
            )}
          >
            <Icon className='size-5 shrink-0 opacity-80' />
            <span className='min-w-0 flex-1'>
              <span className='block truncate text-xs font-medium'>{file.name}</span>
              <span className={cn('block text-[11px]', onDark ? 'opacity-75' : 'text-muted-foreground')}>{formatBytes(file.size)}</span>
            </span>
            {file.url && <Download className='size-4 shrink-0 opacity-70' />}
          </a>
        )
      })}

      <Dialog open={!!preview} onOpenChange={(open) => !open && setPreview(null)}>
        <DialogContent className='max-w-[min(96vw,64rem)] border-0 bg-transparent p-0 shadow-none'>
          <DialogTitle className='sr-only'>{preview?.name}</DialogTitle>
          {preview && (
            <div className='flex flex-col items-center gap-2'>
              <img src={preview.url} alt={preview.name} className='max-h-[80vh] w-auto rounded-lg object-contain' />
              <a href={preview.url} download={preview.name} className='text-sm text-white underline-offset-4 hover:underline'>
                Download {preview.name}
              </a>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

const URL_PATTERN = /(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"])/g

/** Plain text with web links made clickable. React escapes the rest. */
export function MessageText({ text, onDark }: { text: string; onDark?: boolean }) {
  const parts = text.split(URL_PATTERN)
  return (
    <p className='text-sm leading-snug break-words whitespace-pre-wrap'>
      {parts.map((part, index) =>
        index % 2 === 1 ? (
          <a
            key={index}
            href={part}
            target='_blank'
            rel='noopener noreferrer nofollow'
            className={cn('underline underline-offset-2', onDark ? 'decoration-white/60' : 'text-primary')}
          >
            {part}
          </a>
        ) : (
          <span key={index}>{part}</span>
        )
      )}
    </p>
  )
}
