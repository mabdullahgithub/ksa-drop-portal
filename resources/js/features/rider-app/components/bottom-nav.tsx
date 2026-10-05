import { House, ScanLine, UserRound } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useI18n } from '../i18n'

export type Tab = 'home' | 'profile'

type Props = {
  tab: Tab
  onTab: (tab: Tab) => void
  /** Left out for the inventory manager, who scans OUT or IN from Home. */
  onScan?: () => void
  /** Count badge on Home: parcels still with the rider. */
  held?: number
}

/**
 * Floating Liquid Glass tab bar: Home · Scan · Profile. Scan is brand-tinted
 * glass in the middle — it's what a rider does most.
 */
export function BottomNav({ tab, onTab, onScan, held }: Props) {
  const { t } = useI18n()

  return (
    <nav className='pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-[calc(env(safe-area-inset-bottom)+10px)]'>
      {/* Light glass: the list stays visible, blurred, as it scrolls underneath. */}
      <div
        className={cn(
          'glass pointer-events-auto mx-auto grid h-[68px] items-center rounded-full px-2 [--glass-blur:10px] [--glass-fill:rgb(255_255_255/0.22)] dark:[--glass-fill:rgb(38_36_40/0.22)]',
          onScan ? 'max-w-sm grid-cols-3' : 'max-w-[15rem] grid-cols-2'
        )}
      >
        <NavItem active={tab === 'home'} onClick={() => onTab('home')} label={t('nav_home')} badge={held}>
          <House className='h-[22px] w-[22px]' />
        </NavItem>

        {onScan && (
          <div className='flex justify-center'>
            <button
              type='button'
              onClick={onScan}
              className='glass-tint glass-press flex h-14 w-14 items-center justify-center rounded-full'
              aria-label={t('scan')}
            >
              <ScanLine className='h-6 w-6' />
            </button>
          </div>
        )}

        <NavItem active={tab === 'profile'} onClick={() => onTab('profile')} label={t('nav_profile')}>
          <UserRound className='h-[22px] w-[22px]' />
        </NavItem>
      </div>
    </nav>
  )
}

function NavItem({
  active,
  onClick,
  label,
  badge,
  children,
}: {
  active: boolean
  onClick: () => void
  label: string
  badge?: number
  children: React.ReactNode
}) {
  return (
    <button
      type='button'
      onClick={onClick}
      aria-current={active ? 'page' : undefined}
      className='glass-press flex h-full items-center justify-center'
    >
      {/* The active tab sits in its own glass lens. */}
      <span
        className={cn(
          'flex h-[52px] w-[76px] flex-col items-center justify-center gap-0.5 rounded-full text-[11px] font-semibold transition-colors',
          active ? 'glass text-brand' : 'text-muted-foreground'
        )}
      >
        <span className='relative'>
          {children}
          {!!badge && (
            <span className='absolute -end-2.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand px-1 text-[10px] font-bold text-brand-foreground'>
              {badge}
            </span>
          )}
        </span>
        {label}
      </span>
    </button>
  )
}
