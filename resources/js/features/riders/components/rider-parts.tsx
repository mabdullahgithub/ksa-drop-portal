import { formatDistanceToNowStrict } from 'date-fns'
import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { Ban, KeyRound, Link2, ListChecks, LogOut, Pencil, RotateCcw, ScanLine, Smartphone, Trash2, Wallet } from 'lucide-react'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'
import type { ManagerScanFilter, ParcelOutcome, PaymentDirection, RiderCash, RiderPay, RiderRow } from '../data/types'
import type { AccessKind } from './rider-access-dialog'
import { usePermissions } from '@/hooks/use-permissions'

export type RiderDialog =
  | { type: 'add' }
  | { type: 'edit'; rider: RiderRow }
  | { type: AccessKind; rider: RiderRow }
  | { type: 'payments'; rider: RiderRow; direction?: PaymentDirection }
  | { type: 'orders'; rider: RiderRow; outcome?: ParcelOutcome }
  /** An inventory manager's scans. `today`: opened from today's numbers. */
  | { type: 'scans'; rider: RiderRow; filter?: ManagerScanFilter; today?: boolean }
  | { type: 'sign-out' | 'suspend' | 'delete'; rider: RiderRow }

/** What the Riders filters match on for "App". */
export type AppStateKey = 'signed_in' | 'link_sent' | 'not_signed_in' | 'pin_locked'

export function appStateOf(rider: RiderRow): AppStateKey {
  if (rider.device) return 'signed_in'
  if (rider.has_pending_link) return 'link_sent'
  if (rider.pin_locked) return 'pin_locked'
  return 'not_signed_in'
}

export const sar = (n: number) => `SAR ${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

/** What a rider still owes, or that they owe nothing. */
export function CashDue({ cash, className }: { cash: RiderCash | null; className?: string }) {
  if (!cash) return <span className={cn('text-muted-foreground', className)}>—</span>

  if (cash.balance > 0) {
    return <span className={cn('font-semibold tabular-nums text-amber-700 dark:text-amber-400', className)}>{sar(cash.balance)}</span>
  }

  return (
    <span className={cn('tabular-nums text-muted-foreground', className)}>
      {cash.balance < 0 ? `${sar(-cash.balance)} credit` : 'Settled'}
    </span>
  )
}

/** What KSA Drop still owes a rider for their orders, or that it owes nothing. */
export function PayDue({ pay, className }: { pay: RiderPay | null; className?: string }) {
  if (!pay) return <span className={cn('text-muted-foreground', className)}>—</span>

  if (pay.balance > 0) {
    return <span className={cn('font-semibold tabular-nums text-green-700 dark:text-green-400', className)}>{sar(pay.balance)}</span>
  }

  return (
    <span className={cn('tabular-nums text-muted-foreground', className)}>
      {pay.balance < 0 ? `${sar(-pay.balance)} overpaid` : 'Paid up'}
    </span>
  )
}

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}

/**
 * The rider's photo, or their initials when there isn't one. With `online`,
 * a dot in the corner: green while the app is open on their phone.
 */
export function RiderAvatar({
  name,
  photoUrl,
  online,
  className,
}: {
  name: string
  photoUrl: string | null
  online?: boolean
  className?: string
}) {
  const avatar = (
    <Avatar className={cn('size-10', className)}>
      {photoUrl && <AvatarImage src={photoUrl} alt='' className='object-cover' />}
      <AvatarFallback className='bg-orange-100 font-semibold text-orange-800 dark:bg-orange-900/40 dark:text-orange-200'>
        {initials(name)}
      </AvatarFallback>
    </Avatar>
  )

  if (online === undefined) return avatar

  return (
    <span className='relative inline-flex shrink-0'>
      {avatar}
      <span
        className={cn(
          'absolute bottom-[4%] end-[4%] size-[26%] rounded-full ring-2 ring-background',
          online ? 'bg-green-500' : 'bg-muted-foreground/40'
        )}
        title={online ? 'Online now' : 'Offline'}
      />
    </span>
  )
}

function ago(iso: string | null) {
  return iso ? formatDistanceToNowStrict(new Date(iso), { addSuffix: true }) : null
}

/** Is the app on the rider's phone, and how did they get in. */
export function AppState({ rider }: { rider: RiderRow }) {
  if (rider.device) {
    const phone = rider.device.platform === 'ios' ? 'iPhone' : rider.device.platform === 'android' ? 'Android' : 'phone'
    return (
      <div className='text-sm'>
        <div className='flex items-center gap-1.5 font-medium text-green-700 dark:text-green-400'>
          <Smartphone className='h-3.5 w-3.5' />
          Signed in on {phone}
        </div>
        <div className='text-xs text-muted-foreground'>
          {rider.device.sign_in_method === 'pin' ? 'with PIN' : 'with link'}
          {!rider.device.standalone && ' · in browser'}
          {rider.online ? (
            <span className='font-medium text-green-700 dark:text-green-400'> · online now</span>
          ) : (
            rider.device.last_seen_at && ` · seen ${ago(rider.device.last_seen_at)}`
          )}
        </div>
      </div>
    )
  }

  if (rider.has_pending_link) {
    return <span className='text-sm text-amber-700 dark:text-amber-400'>Link sent, not opened yet</span>
  }

  return (
    <span className='text-sm text-muted-foreground'>
      Not signed in{rider.pin_locked && <span className='text-red-600'> · PIN locked</span>}
    </span>
  )
}

export function RowActions({ rider, onPick }: { rider: RiderRow; onPick: (dialog: RiderDialog) => void }) {
  // Every item in this menu is a permission of its own.
  const { can } = usePermissions()
  const active = rider.status === 'active'
  // An inventory manager has no parcels, cash or pay of their own.
  const isRider = rider.role === 'rider'

  const items = {
    link: active && can('send rider app link'),
    pin: active && can('set rider pin'),
    orders: isRider && can('view rider parcels'),
    scans: !isRider && can('view inventory manager scans'),
    payments: isRider && can('view rider payments'),
    edit: can('edit riders'),
    signOut: !!rider.device && can('sign rider out'),
    suspend: can('suspend riders'),
    remove: can('delete riders'),
  }
  const upper = items.link || items.pin || items.orders || items.scans || items.payments || items.edit || items.signOut
  const lower = items.suspend || items.remove

  // Nothing to offer: no menu at all.
  if (!upper && !lower) return null

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'>
          <DotsHorizontalIcon className='h-4 w-4' />
          <span className='sr-only'>Open menu</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-48'>
        {items.link && (
          <DropdownMenuItem onClick={() => onPick({ type: 'link', rider })}>
            Send app link
            <DropdownMenuShortcut><Link2 size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {items.pin && (
          <DropdownMenuItem onClick={() => onPick({ type: 'pin', rider })}>
            {rider.has_pin ? 'New PIN' : 'Give a PIN'}
            <DropdownMenuShortcut><KeyRound size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {items.orders && (
          <DropdownMenuItem onClick={() => onPick({ type: 'orders', rider })}>
            Orders
            <DropdownMenuShortcut><ListChecks size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {items.scans && (
          <DropdownMenuItem onClick={() => onPick({ type: 'scans', rider })}>
            Scans
            <DropdownMenuShortcut><ScanLine size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {items.payments && (
          <DropdownMenuItem onClick={() => onPick({ type: 'payments', rider })}>
            Cash &amp; pay
            <DropdownMenuShortcut><Wallet size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {items.edit && (
          <DropdownMenuItem onClick={() => onPick({ type: 'edit', rider })}>
            Edit details
            <DropdownMenuShortcut><Pencil size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {items.signOut && (
          <DropdownMenuItem onClick={() => onPick({ type: 'sign-out', rider })}>
            Sign phone out
            <DropdownMenuShortcut><LogOut size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {upper && lower && <DropdownMenuSeparator />}
        {items.suspend && (
          <DropdownMenuItem onClick={() => onPick({ type: 'suspend', rider })}>
            {active ? 'Suspend' : 'Reactivate'}
            <DropdownMenuShortcut>{active ? <Ban size={16} /> : <RotateCcw size={16} />}</DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {items.remove && (
          <DropdownMenuItem className='text-red-500!' onClick={() => onPick({ type: 'delete', rider })}>
            Remove
            <DropdownMenuShortcut><Trash2 size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
