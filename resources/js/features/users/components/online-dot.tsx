import { cn } from '@/lib/utils'
import { useUsers } from './users-provider'

/** Green while the user is on the portal, grey otherwise. */
export function OnlineDot({ userId, className }: { userId: number; className?: string }) {
  const online = useUsers().online.has(userId)

  return (
    <span
      className={cn(
        'inline-block size-2 shrink-0 rounded-full',
        online ? 'bg-green-500' : 'bg-muted-foreground/40',
        className
      )}
      title={online ? 'Online now' : 'Offline'}
    />
  )
}
