import { useState, useRef, useEffect, KeyboardEvent } from 'react'
import { X, Plus, Loader2 } from 'lucide-react'
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
import { Separator } from '@/components/ui/separator'
import { useAvailableTags } from '@/hooks/useTags'
import { type Tag } from '@/features/tags/data/schema'

function hexToRgba(hex: string, alpha: number) {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  orderNumber: string
  currentTags: string[]
  onSave: (tags: string[]) => Promise<void>
  isSaving?: boolean
}

export function OrderTagsDialog({
  open,
  onOpenChange,
  orderNumber,
  currentTags,
  onSave,
  isSaving = false,
}: Props) {
  const { tags: availableTags, loading } = useAvailableTags()
  const [selected, setSelected] = useState<string[]>(currentTags)
  const [freeInput, setFreeInput] = useState('')
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (open) {
      setSelected(currentTags)
      setFreeInput('')
    }
  }, [open])

  const handleOpenChange = (open: boolean) => {
    onOpenChange(open)
  }

  const toggle = (tagName: string) => {
    setSelected((prev) =>
      prev.includes(tagName) ? prev.filter((t) => t !== tagName) : [...prev, tagName]
    )
  }

  const removeTag = (tagName: string) => {
    setSelected((prev) => prev.filter((t) => t !== tagName))
  }

  const addFreeTag = () => {
    const val = freeInput.trim()
    if (!val || selected.includes(val)) { setFreeInput(''); return }
    setSelected((prev) => [...prev, val])
    setFreeInput('')
    inputRef.current?.focus()
  }

  const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault()
      addFreeTag()
    } else if (e.key === 'Backspace' && freeInput === '' && selected.length > 0) {
      setSelected((prev) => prev.slice(0, -1))
    }
  }

  const handleSave = async () => {
    await onSave(selected)
  }

  // Predefined tags not currently selected
  const unselectedPredefined = availableTags.filter((t) => !selected.includes(t.name))

  // Find meta for a tag name
  const getMeta = (name: string): Tag | undefined =>
    availableTags.find((t) => t.name === name)

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Manage Tags</DialogTitle>
          <DialogDescription>
            Assign tags to order <span className='font-medium text-foreground'>{orderNumber}</span>.
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-4 py-1'>
          {/* Current tags */}
          <div>
            <p className='mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wide'>
              Applied tags
            </p>
            {selected.length === 0 ? (
              <p className='text-xs text-muted-foreground italic'>No tags applied yet.</p>
            ) : (
              <div className='flex flex-wrap gap-1.5'>
                {selected.map((name) => {
                  const meta = getMeta(name)
                  return (
                    <span
                      key={name}
                      className='inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium'
                      style={
                        meta
                          ? {
                              backgroundColor: hexToRgba(meta.color, 0.15),
                              color: meta.color,
                              border: `1px solid ${hexToRgba(meta.color, 0.3)}`,
                            }
                          : undefined
                      }
                    >
                      {!meta && (
                        <span className='inline-block h-2 w-2 rounded-full bg-muted-foreground/60' />
                      )}
                      {name}
                      <button
                        type='button'
                        className='rounded-full opacity-60 hover:opacity-100 transition-opacity'
                        onClick={() => removeTag(name)}
                        disabled={isSaving}
                      >
                        <X size={10} />
                      </button>
                    </span>
                  )
                })}
              </div>
            )}
          </div>

          <Separator />

          {/* Predefined tags */}
          <div>
            <p className='mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wide'>
              Predefined tags
            </p>
            {loading ? (
              <div className='flex items-center gap-2 text-xs text-muted-foreground'>
                <Loader2 size={12} className='animate-spin' /> Loading tags...
              </div>
            ) : availableTags.length === 0 ? (
              <p className='text-xs text-muted-foreground italic'>No predefined tags. Create some on the Tags page.</p>
            ) : (
              <div className='flex flex-wrap gap-1.5'>
                {availableTags.map((tag) => {
                  const isActive = selected.includes(tag.name)
                  return (
                    <button
                      key={tag.id}
                      type='button'
                      onClick={() => toggle(tag.name)}
                      disabled={isSaving}
                      className='inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium transition-all'
                      style={
                        isActive
                          ? {
                              backgroundColor: hexToRgba(tag.color, 0.2),
                              color: tag.color,
                              border: `1.5px solid ${tag.color}`,
                            }
                          : {
                              backgroundColor: hexToRgba(tag.color, 0.07),
                              color: tag.color,
                              border: `1px dashed ${hexToRgba(tag.color, 0.4)}`,
                              opacity: 0.75,
                            }
                      }
                    >
                      <span
                        className='inline-block h-1.5 w-1.5 rounded-full'
                        style={{ backgroundColor: tag.color }}
                      />
                      {tag.name}
                      {isActive && <X size={9} className='opacity-70' />}
                      {!isActive && <Plus size={9} className='opacity-50' />}
                    </button>
                  )
                })}
                {/* Show already-applied free tags that aren't in predefined */}
                {unselectedPredefined.length === 0 && availableTags.length > 0 && (
                  <p className='text-xs text-muted-foreground'>All tags applied.</p>
                )}
              </div>
            )}
          </div>

          <Separator />

          {/* Free-form input */}
          <div>
            <p className='mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wide'>
              Custom tag
            </p>
            <div className='flex gap-2'>
              <Input
                ref={inputRef}
                placeholder='Type a tag and press Enter...'
                value={freeInput}
                onChange={(e) => setFreeInput(e.target.value)}
                onKeyDown={handleKeyDown}
                disabled={isSaving}
                className='text-sm'
              />
              <Button
                type='button'
                variant='outline'
                size='sm'
                onClick={addFreeTag}
                disabled={!freeInput.trim() || isSaving}
              >
                Add
              </Button>
            </div>
            <p className='mt-1 text-xs text-muted-foreground'>Press Enter or comma to add</p>
          </div>
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)} disabled={isSaving}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={isSaving}>
            {isSaving ? <><Loader2 size={14} className='me-1.5 animate-spin' />Saving...</> : 'Save tags'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
