import { Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { type Tag } from '../data/schema'
import { useTags } from './tags-provider'

function hexToRgba(hex: string, alpha: number) {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

export function TagCard({ tag }: { tag: Tag }) {
  const { setOpen, setCurrentRow } = useTags()

  return (
    <div className='group relative flex flex-col overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-md'>
      {/* Color band */}
      <div
        className='h-1.5 w-full flex-shrink-0'
        style={{ backgroundColor: tag.color }}
      />

      <div className='flex flex-1 flex-col gap-3 p-4'>
        {/* Header row */}
        <div className='flex items-start justify-between gap-2'>
          <div className='flex items-center gap-2.5'>
            {/* Color swatch chip */}
            <span
              className='inline-flex h-7 min-w-16 items-center justify-center rounded-full px-3 text-xs font-semibold tracking-wide'
              style={{
                backgroundColor: hexToRgba(tag.color, 0.15),
                color: tag.color,
                border: `1px solid ${hexToRgba(tag.color, 0.3)}`,
              }}
            >
              {tag.name}
            </span>
          </div>

          {/* Actions — visible on hover */}
          <div className='flex gap-1 opacity-0 transition-opacity group-hover:opacity-100'>
            <Can permission='edit tags'>
              <Button
                variant='ghost'
                size='icon'
                className='h-7 w-7'
                onClick={() => { setCurrentRow(tag); setOpen('edit') }}
              >
                <Pencil size={13} />
              </Button>
            </Can>
            <Can permission='delete tags'>
              <Button
                variant='ghost'
                size='icon'
                className='h-7 w-7 text-destructive hover:text-destructive'
                onClick={() => { setCurrentRow(tag); setOpen('delete') }}
              >
                <Trash2 size={13} />
              </Button>
            </Can>
          </div>
        </div>

        {/* Description */}
        {tag.description ? (
          <p className='line-clamp-2 text-xs text-muted-foreground leading-relaxed'>
            {tag.description}
          </p>
        ) : (
          <p className='text-xs text-muted-foreground/40 italic'>No description</p>
        )}

        {/* Footer */}
        <div className='mt-auto flex items-center gap-1.5 pt-1'>
          <span
            className='inline-block h-2 w-2 rounded-full'
            style={{ backgroundColor: tag.color }}
          />
          <span className='font-mono text-[10px] text-muted-foreground'>{tag.color}</span>
        </div>
      </div>
    </div>
  )
}
