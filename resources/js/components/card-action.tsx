import { type ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'

type CardActionProps = {
  /** What the button does: its tooltip and its name for screen readers. */
  label: string
  onClick: () => void
  destructive?: boolean
  /** The icon. */
  children: ReactNode
}

/** A small icon-only button for the action row at the bottom of a card. */
export function CardAction({ label, onClick, destructive, children }: CardActionProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          variant='outline'
          size='icon'
          className={cn(
            'size-8 [&_svg]:size-3.5',
            destructive &&
              'border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive'
          )}
          onClick={onClick}
          aria-label={label}
        >
          {children}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}
