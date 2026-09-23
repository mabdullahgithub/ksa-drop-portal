import { Monitor, Globe, User as UserIcon } from 'lucide-react'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { type DeletedByInfo } from '@/hooks/useRecycleBin'

/**
 * Shows who deleted a record, with the full forensic detail (IP, device, raw
 * user agent) behind a click rather than crowding the row.
 */
export function DeletedByCell({ info }: { info: DeletedByInfo | null }) {
  if (!info) {
    // Rows deleted before auditing existed, and anything deleted by a
    // scheduled job or console command, have no actor.
    return <span className='text-muted-foreground'>—</span>
  }

  const label = info.name ?? 'Unknown user'

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type='button'
          className='hover:text-foreground text-start underline-offset-2 hover:underline'
        >
          {label}
        </button>
      </PopoverTrigger>
      <PopoverContent align='start' className='w-80 space-y-3 text-xs'>
        <div className='flex items-start gap-2'>
          <UserIcon className='text-muted-foreground mt-0.5 h-3.5 w-3.5 shrink-0' />
          <div className='min-w-0'>
            <p className='font-medium'>{label}</p>
            {info.email && <p className='text-muted-foreground truncate'>{info.email}</p>}
          </div>
        </div>

        <div className='flex items-start gap-2'>
          <Globe className='text-muted-foreground mt-0.5 h-3.5 w-3.5 shrink-0' />
          <div className='min-w-0'>
            <p className='text-muted-foreground'>IP address</p>
            <p className='font-mono'>{info.ip ?? '—'}</p>
          </div>
        </div>

        <div className='flex items-start gap-2'>
          <Monitor className='text-muted-foreground mt-0.5 h-3.5 w-3.5 shrink-0' />
          <div className='min-w-0 space-y-1'>
            <p className='text-muted-foreground'>Device</p>
            <p>{info.device ?? '—'}</p>
            {info.user_agent && (
              <p className='text-muted-foreground break-all font-mono text-[10px] leading-relaxed'>
                {info.user_agent}
              </p>
            )}
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}
