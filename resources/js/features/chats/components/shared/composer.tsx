import { useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'
import { FileText, ImagePlus, Lock, Paperclip, Send, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { ACCEPT_FILES, MAX_BODY, MAX_FILE_MB, MAX_FILES } from '../../constants'
import { formatBytes } from './chat-bits'

export type ComposerMode = 'message' | 'note'

/**
 * The message box, ported from the template's composer and finished:
 * Enter sends and Shift+Enter breaks the line; files come from the
 * paperclip, the image button, a paste or a drop; and on the team's side a
 * switch writes an internal note instead of a reply.
 *
 * When writing is not possible the reason is said in place of the box, so
 * "why can't I type?" never needs asking.
 */
export function Composer({
  onSend,
  onTyping,
  canReply = true,
  canNote = false,
  disabledReason,
  placeholder = 'Type your message…',
  autoFocusKey,
}: {
  onSend: (input: { body: string; kind: ComposerMode; files: File[] }) => Promise<boolean>
  onTyping?: () => void
  canReply?: boolean
  canNote?: boolean
  /** Shown instead of the box. */
  disabledReason?: React.ReactNode
  placeholder?: string
  /** Changing it (another conversation) clears the draft and focuses the box. */
  autoFocusKey?: string | number
}) {
  const [body, setBody] = useState('')
  const [files, setFiles] = useState<File[]>([])
  const [mode, setMode] = useState<ComposerMode>(canReply ? 'message' : 'note')
  const [dragging, setDragging] = useState(false)
  const textarea = useRef<HTMLTextAreaElement>(null)
  const fileInput = useRef<HTMLInputElement>(null)
  const imageInput = useRef<HTMLInputElement>(null)

  useEffect(() => {
    setBody('')
    setFiles([])
    setMode(canReply ? 'message' : 'note')
    textarea.current?.focus()
  }, [autoFocusKey, canReply])

  // Grows with the text up to about six lines.
  useEffect(() => {
    const el = textarea.current
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`
  }, [body])

  if (disabledReason || (!canReply && !canNote)) {
    return (
      <div className='flex items-start gap-2 border-t bg-muted/40 px-4 py-3 text-xs text-muted-foreground'>
        <Lock className='mt-0.5 size-3.5 shrink-0' />
        <div>{disabledReason ?? 'You can read this chat but not reply to it.'}</div>
      </div>
    )
  }

  const add = (incoming: FileList | File[] | null) => {
    if (!incoming) return
    const list = Array.from(incoming)
    const tooBig = list.filter((f) => f.size > MAX_FILE_MB * 1024 * 1024)
    if (tooBig.length) toast.error(`Files must be under ${MAX_FILE_MB} MB: ${tooBig.map((f) => f.name).join(', ')}`)
    const next = [...files, ...list.filter((f) => f.size <= MAX_FILE_MB * 1024 * 1024)]
    if (next.length > MAX_FILES) toast.error(`Up to ${MAX_FILES} files per message`)
    setFiles(next.slice(0, MAX_FILES))
  }

  const submit = async () => {
    const text = body.trim()
    if (!text && files.length === 0) return
    if (text.length > MAX_BODY) {
      toast.error(`Messages are limited to ${MAX_BODY} characters`)
      return
    }
    const sent = { body: text, kind: mode, files }
    // Cleared at once: the bubble is already on screen and can be retried from there.
    setBody('')
    setFiles([])
    textarea.current?.focus()
    await onSend(sent)
  }

  const note = mode === 'note'

  return (
    <div
      className={cn('relative border-t bg-background px-3 py-2.5', note && 'bg-amber-50/60 dark:bg-amber-950/20')}
      onDragOver={(e) => {
        if (e.dataTransfer.types.includes('Files')) {
          e.preventDefault()
          setDragging(true)
        }
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={(e) => {
        e.preventDefault()
        setDragging(false)
        add(e.dataTransfer.files)
      }}
    >
      {dragging && (
        <div className='pointer-events-none absolute inset-1 z-10 flex items-center justify-center rounded-md border-2 border-dashed border-primary bg-background/90 text-sm font-medium text-primary'>
          Drop files to attach
        </div>
      )}

      {canNote && (
        <div className='mb-1.5 flex items-center gap-1 text-[11px]'>
          {canReply && (
            <button
              type='button'
              onClick={() => setMode('message')}
              className={cn('rounded-full px-2 py-0.5 font-medium transition-colors', !note ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted')}
            >
              Reply
            </button>
          )}
          <button
            type='button'
            onClick={() => setMode('note')}
            className={cn(
              'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium transition-colors',
              note ? 'bg-amber-500 text-white dark:bg-amber-600' : 'text-muted-foreground hover:bg-muted'
            )}
          >
            <Lock className='size-2.5' /> Internal note
          </button>
          {note && <span className='ms-1 text-amber-700 dark:text-amber-300'>Only the team sees notes</span>}
        </div>
      )}

      {files.length > 0 && (
        <div className='mb-2 flex flex-wrap gap-2'>
          {files.map((file, index) => (
            <FileChip key={`${file.name}-${index}`} file={file} onRemove={() => setFiles(files.filter((_, i) => i !== index))} />
          ))}
        </div>
      )}

      <div
        className={cn(
          'flex items-end gap-1 rounded-md border bg-card px-1.5 py-1 focus-within:ring-1 focus-within:ring-ring',
          note && 'border-amber-300 dark:border-amber-800'
        )}
      >
        <Button type='button' size='icon' variant='ghost' className='size-8 shrink-0' onClick={() => fileInput.current?.click()} aria-label='Attach a file'>
          <Paperclip className='size-4 text-muted-foreground' />
        </Button>
        <Button type='button' size='icon' variant='ghost' className='hidden size-8 shrink-0 sm:inline-flex' onClick={() => imageInput.current?.click()} aria-label='Attach a photo'>
          <ImagePlus className='size-4 text-muted-foreground' />
        </Button>

        <label className='min-w-0 flex-1'>
          <span className='sr-only'>{note ? 'Internal note' : 'Message'}</span>
          <textarea
            ref={textarea}
            value={body}
            rows={1}
            maxLength={MAX_BODY}
            onChange={(e) => {
              setBody(e.target.value)
              if (!note) onTyping?.()
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                e.preventDefault()
                void submit()
              }
            }}
            onPaste={(e) => {
              const pasted = Array.from(e.clipboardData.files)
              if (pasted.length) {
                e.preventDefault()
                add(pasted)
              }
            }}
            placeholder={note ? 'Write a note for the team…' : placeholder}
            className='block max-h-40 min-h-8 w-full resize-none bg-transparent px-1 py-1.5 text-[13px] outline-none placeholder:text-muted-foreground'
          />
        </label>

        <Button
          type='button'
          size='icon'
          className={cn('size-8 shrink-0', note && 'bg-amber-500 text-white hover:bg-amber-600')}
          onClick={() => void submit()}
          disabled={!body.trim() && files.length === 0}
          aria-label={note ? 'Add note' : 'Send'}
        >
          <Send className='size-4' />
        </Button>
      </div>

      <div className='mt-1 flex justify-between px-1 text-[11px] text-muted-foreground'>
        <span className='hidden sm:inline'>Enter to send · Shift+Enter for a new line</span>
        {body.length > MAX_BODY * 0.8 && <span>{MAX_BODY - body.length} left</span>}
      </div>

      <input ref={fileInput} type='file' multiple accept={ACCEPT_FILES} className='hidden' onChange={(e) => { add(e.target.files); e.target.value = '' }} />
      <input ref={imageInput} type='file' multiple accept='image/*' className='hidden' onChange={(e) => { add(e.target.files); e.target.value = '' }} />
    </div>
  )
}

function FileChip({ file, onRemove }: { file: File; onRemove: () => void }) {
  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    if (!file.type.startsWith('image/')) return
    const next = URL.createObjectURL(file)
    setUrl(next)
    return () => URL.revokeObjectURL(next)
  }, [file])

  return (
    <div className='relative flex max-w-48 items-center gap-2 rounded-md border bg-card py-1 ps-1 pe-6 text-xs'>
      {url ? <img src={url} alt='' className='size-8 rounded object-cover' /> : <FileText className='size-6 shrink-0 text-muted-foreground' />}
      <span className='min-w-0'>
        <span className='block truncate font-medium'>{file.name}</span>
        <span className='text-muted-foreground'>{formatBytes(file.size)}</span>
      </span>
      <button type='button' onClick={onRemove} className='absolute end-1 top-1 rounded p-0.5 text-muted-foreground hover:bg-muted' aria-label={`Remove ${file.name}`}>
        <X className='size-3' />
      </button>
    </div>
  )
}
