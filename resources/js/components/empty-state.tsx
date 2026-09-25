import { BotAvatar, type BotAvatarState, type BotAvatarType } from 'bot-avatars'
import { cn } from '@/lib/utils'

const BOT_SIZE = { sm: 40, md: 56, lg: 72 } as const

type EmptyStateProps = {
  /** Bot shape. Keep one shape per kind of data so the same list always looks the same. */
  bot?: BotAvatarType
  /** `sleeping` for "nothing yet", `default` for "nothing matches". */
  state?: BotAvatarState
  size?: keyof typeof BOT_SIZE
  title: React.ReactNode
  description?: React.ReactNode
  /** Usually a single button that fixes the empty state (create, clear filters). */
  action?: React.ReactNode
  className?: string
}

/**
 * The shared "there is nothing here" block. Use one per screen at most: every
 * bot is its own animated canvas, so never render this per table row or inside
 * a combobox/command list.
 */
export function EmptyState({
  bot = 'clover',
  state = 'default',
  size = 'md',
  title,
  description,
  action,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center gap-1 py-8 text-center',
        className
      )}
    >
      <div className='mb-2' aria-hidden='true'>
        <BotAvatar type={bot} state={state} size={BOT_SIZE[size]} />
      </div>
      <p className='text-sm font-medium text-muted-foreground'>{title}</p>
      {description && (
        <p className='max-w-sm text-xs text-muted-foreground'>{description}</p>
      )}
      {action && <div className='mt-3'>{action}</div>}
    </div>
  )
}
