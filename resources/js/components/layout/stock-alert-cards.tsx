import { useState } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { AlertTriangle, ArrowRight, Minus, PackageX, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { usePermissions } from '@/hooks/use-permissions'
import type { PageProps, StockAlertProduct } from '@/types'

const STORAGE_KEY = 'stock-alerts-dismissed'

type Dismissed = { session: string; closed: string[]; minimized?: boolean }

function readDismissed(session: string): Dismissed {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null') as Dismissed | null
    // Closed during an earlier sign-in: show the cards again.
    if (saved?.session === session && Array.isArray(saved.closed)) return saved
  } catch {
    // Storage blocked or unreadable: nothing was dismissed.
  }
  return { session, closed: [] }
}

// Changes when the product drops another unit, so a closed card comes back
// at 3, again at 2, again at 1, and again when there is none.
const keyOf = (product: StockAlertProduct) => `${product.id}:${product.left}`

/**
 * Stock warnings as a stack of mini frosted-glass cards in the bottom corner: one card per
 * fulfilment-client product that is down to 3, 2 or 1 (amber) or has none left
 * (red, shown first). Clients see their own products, the team sees every
 * client's. Closing the top card reveals the next; a closed card stays away
 * until the next sign-in or until that product's stock drops again. The whole
 * stack can be minimized to a pill and opened again; hovering fans the stack
 * out a little to show there is more underneath, and clicking the top card
 * sends it to the back to bring up the next.
 */
export function StockAlertCards() {
  const { stockAlerts, auth } = usePage<PageProps>().props
  const { can, hasRole } = usePermissions()
  const session = stockAlerts?.session ?? ''
  const [dismissed, setDismissed] = useState<Dismissed>(() => readDismissed(session))
  const [hovered, setHovered] = useState(false)
  // How many times the stack was flicked on: the top card goes to the back.
  const [turn, setTurn] = useState(0)

  if (!stockAlerts) return null

  const closed = dismissed.session === session ? dismissed.closed : []
  const minimized = dismissed.session === session && !!dismissed.minimized
  // The server sends them emptiest first, so the out-of-stock ones lead.
  const waiting = stockAlerts.products.filter((product) => !closed.includes(keyOf(product)))
  const front = waiting.length ? turn % waiting.length : 0
  // The stack as it sits now, top card first.
  const open = [...waiting.slice(front), ...waiting.slice(0, front)]
  const product = open[0]

  if (!product) return null

  const save = (next: Dismissed) => {
    setDismissed(next)
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    } catch {
      // Storage blocked: it holds for this page only.
    }
  }
  const close = (keys: string[]) => save({ session, closed: [...closed, ...keys], minimized })
  const minimize = (value: boolean) => {
    setHovered(false)
    save({ session, closed, minimized: value })
  }

  const staff = stockAlerts.audience === 'staff'
  const out = product.left === 0
  const Icon = out ? PackageX : AlertTriangle
  const behind = Math.min(open.length - 1, 2)

  const href = staff
    ? product.client && can('view client details') && can('view client products')
      ? `${route('client.show', product.client.id)}?tab=inventory`
      : null
    : auth.portal_features?.includes('inventory')
      ? route('portal.inventory')
      : null

  // Clients have the guide-video button in this corner; sit above it.
  const corner = cn('fixed end-4 z-40', hasRole('client') ? 'bottom-20' : 'bottom-4')
  // How far each waiting card peeks out: a sliver at rest, more on hover.
  const peek = hovered ? 14 : 6

  if (minimized) {
    const anyOut = open.some((item) => item.left === 0)
    const PillIcon = anyOut ? PackageX : AlertTriangle

    // Same look as the dashboard and WhatsApp stat cards (see StatCard): a
    // solid fill, soft blobs, and the icon in a bubble overhanging the corner.
    return (
      <button
        type='button'
        onClick={() => minimize(false)}
        aria-label={`Show ${open.length} stock ${open.length === 1 ? 'alert' : 'alerts'}`}
        className={cn(
          corner,
          'group w-24 pt-2 pe-1.5 text-start focus-visible:outline-none animate-in fade-in zoom-in-95 duration-200'
        )}
      >
        <span className='relative block overflow-hidden rounded-lg bg-red-600 px-2.5 pb-1.5 pt-2.5 shadow-lg transition-shadow group-hover:shadow-xl group-focus-visible:ring-2 group-focus-visible:ring-ring'>
          <span aria-hidden className='pointer-events-none absolute -bottom-4 -start-3 h-10 w-10 rounded-full bg-black/10' />
          <span aria-hidden className='pointer-events-none absolute bottom-2.5 start-6 h-2 w-2 rounded-full bg-black/10' />
          <span aria-hidden className='pointer-events-none absolute -top-5 start-3 h-9 w-9 rounded-full bg-white/10' />

          <span className='relative block'>
            <span className='block text-base font-bold leading-none tracking-tight text-white'>{open.length}</span>
            <span className='mt-1 block truncate text-[10px] font-medium leading-snug text-white/85'>
              Stock {open.length === 1 ? 'alert' : 'alerts'}
            </span>
          </span>
        </span>

        <span className='pointer-events-none absolute end-2.5 top-0 z-10'>
          <span className='grid h-6 w-6 place-items-center rounded-full rounded-es-none bg-red-800 text-white shadow-md ring-1 ring-background'>
            <PillIcon className='h-3 w-3' />
          </span>
        </span>
      </button>
    )
  }

  return (
    <div
      className={cn(corner, 'w-[min(17rem,calc(100vw-2rem))]')}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
    >
      {/* The cards waiting underneath, peeking out above the top one. */}
      {Array.from({ length: behind }, (_, index) => (
        <div
          key={index}
          aria-hidden
          className={cn(
            'absolute inset-x-0 top-0 h-full rounded-xl border shadow-sm backdrop-blur-sm transition-transform duration-200 ease-out',
            // Each waiting card in its own colour, so a low one behind an out one shows amber.
            open[behind - index].left === 0
              ? 'border-red-500/25 bg-red-500/8'
              : 'border-amber-500/25 bg-amber-500/8'
          )}
          style={{
            transform: `translateY(-${(behind - index) * peek}px) scale(${1 - (behind - index) * 0.05})`,
            opacity: 1 - (behind - index) * 0.25,
          }}
        />
      ))}

      <div
        key={keyOf(product)}
        role={out ? 'alert' : 'status'}
        // Clicking the card flicks it to the back and brings up the next one.
        onClick={open.length > 1 ? () => setTurn(front + 1) : undefined}
        className={cn(
          open.length > 1 && 'cursor-pointer',
          'relative overflow-hidden rounded-xl border text-foreground shadow-lg backdrop-blur-md backdrop-saturate-150 animate-in fade-in slide-in-from-bottom-2 duration-200',
          // The whole card carries the colour, faintly: red when out, amber when low.
          out ? 'border-red-500/25 bg-red-500/12' : 'border-amber-500/25 bg-amber-500/12'
        )}
      >

        <div className='flex items-start gap-2.5 p-3'>
          <div
            className={cn(
              'flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
              out
                ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                : 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'
            )}
          >
            <Icon className='h-3.5 w-3.5' />
          </div>

          <div className='min-w-0 flex-1'>
            <div
              className={cn(
                'text-[10px] font-semibold uppercase tracking-wide',
                out ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'
              )}
            >
              {out ? 'Out of stock' : `Low stock · ${product.left} left`}
            </div>
            <div className='truncate text-[13px] font-semibold leading-5' title={product.name}>
              {product.name}
            </div>
            <div className='truncate text-[11px] text-muted-foreground'>
              {staff && product.client ? `${product.client.company_name} · ` : ''}
              {product.sku || product.product_code}
            </div>

            {href && (
              <Link
                href={href}
                onClick={(event) => event.stopPropagation()}
                className='mt-1.5 inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline'
              >
                {staff ? 'Open client inventory' : 'Open inventory'}
                <ArrowRight className='h-3 w-3' />
              </Link>
            )}
          </div>

          <div className='-me-1 -mt-1 flex shrink-0 items-center'>
            <button
              type='button'
              onClick={(event) => {
                event.stopPropagation()
                minimize(true)
              }}
              aria-label='Minimize'
              title='Minimize'
              className='rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
            >
              <Minus className='h-3.5 w-3.5' />
            </button>
            <button
              type='button'
              onClick={(event) => {
                event.stopPropagation()
                close([keyOf(product)])
              }}
              aria-label='Dismiss'
              title='Dismiss'
              className='rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
            >
              <X className='h-3.5 w-3.5' />
            </button>
          </div>
        </div>

        {open.length > 1 && (
          <div className='flex items-center justify-between border-t border-current/10 px-3 py-1.5 text-[11px] text-muted-foreground'>
            <span>
              {front + 1} of {open.length} · click for next
            </span>
            <button
              type='button'
              onClick={(event) => {
                event.stopPropagation()
                close(open.map(keyOf))
              }}
              className='font-medium hover:text-foreground hover:underline'
            >
              Dismiss all
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
