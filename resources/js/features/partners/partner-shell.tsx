import { useState } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { ArrowRight, Check, Clock, Coins, Copy, Gift, Link2, Pencil, TriangleAlert } from 'lucide-react'
import { toast } from 'sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { usePermissions } from '@/hooks/use-permissions'
import { cn } from '@/lib/utils'
import { TIER_LABEL, formatPoints, type PartnerSummary } from './types'

/** The frame around every page in the partner's own area. */
export function PartnerShell({
  title,
  description,
  partner,
  actions,
  children,
}: {
  title: string
  description?: string
  partner: PartnerSummary
  actions?: React.ReactNode
  children: React.ReactNode
}) {
  return (
    <AuthenticatedLayout>
      <Head title={title} />
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>
      <Main>
        <div className='mb-6 flex flex-wrap items-start justify-between gap-3'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>{title}</h1>
            {description && <p className='text-muted-foreground'>{description}</p>}
          </div>
          <div className='flex items-center gap-2'>
            <Badge variant='outline' className='gap-1.5'>
              <TierDot tier={partner.tier} /> {TIER_LABEL[partner.tier]}
            </Badge>
            <Badge variant='secondary' className='tabular-nums'>{formatPoints(partner.points_balance)} pts</Badge>
            {actions}
          </div>
        </div>
        {partner.status === 'pending' && (
          <div className='mb-6 flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 text-sm'>
            <Clock className='mt-0.5 h-4 w-4 shrink-0 text-amber-600' />
            <div>
              <div className='font-medium'>Your application is under review</div>
              <p className='mt-0.5 text-muted-foreground'>
                Have a look around while you wait. Your referral link, points and payouts switch on once we approve you, usually within two business days.
              </p>
            </div>
          </div>
        )}
        {children}
      </Main>
      <FloatingReferral partner={partner} />
    </AuthenticatedLayout>
  )
}

export function TierDot({ tier }: { tier: PartnerSummary['tier'] }) {
  const color = { bronze: 'bg-amber-700', silver: 'bg-slate-400', gold: 'bg-yellow-400' }[tier]
  return <span className={`inline-block h-2 w-2 rounded-full ${color}`} />
}

export function CopyButton({ value, label = 'Copy', size = 'sm' }: { value: string; label?: string; size?: 'sm' | 'default' | 'icon' }) {
  const [copied, setCopied] = useState(false)
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      toast.success('Copied')
      setTimeout(() => setCopied(false), 1500)
    } catch {
      toast.error('Could not copy. Select and copy it by hand.')
    }
  }
  return (
    <Button type='button' variant='outline' size={size} onClick={copy}>
      {copied ? <Check className='h-4 w-4' /> : <Copy className='h-4 w-4' />}
      {size !== 'icon' && label}
    </Button>
  )
}

/** Copy a value with a short confirmation; the icon swaps with a little pop. */
function useCopy(value: string, message: string) {
  const [copied, setCopied] = useState(false)
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      toast.success(message)
      setTimeout(() => setCopied(false), 1600)
    } catch {
      toast.error('Could not copy. Select and copy it by hand.')
    }
  }
  return { copied, copy }
}

function CopyIcon({ copied }: { copied: boolean }) {
  return copied
    ? <Check key='ok' className='h-4 w-4 animate-in zoom-in-50 duration-200' />
    : <Copy key='cp' className='h-4 w-4' />
}

/**
 * The partner's referral code as a tile. The link itself stays out of sight:
 * "Copy link" puts it on the clipboard. A partner may rename the code once.
 */
export function ReferralBox({ partner }: { partner: PartnerSummary }) {
  const [editing, setEditing] = useState(false)
  const code = useCopy(partner.code, 'Code copied')
  const link = useCopy(partner.referral_url, 'Link copied')

  return (
    <>
      <div
        data-referral-box
        className='group relative overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 via-orange-600 to-amber-700 p-5 text-white shadow-lg animate-in fade-in slide-in-from-bottom-2 duration-500'
      >
        <span aria-hidden className='pointer-events-none absolute -end-10 -top-12 h-40 w-40 rounded-full bg-white/10 transition-transform duration-700 group-hover:scale-125' />
        <span aria-hidden className='pointer-events-none absolute -bottom-14 start-1/3 h-36 w-36 rounded-full bg-black/10 transition-transform duration-700 group-hover:scale-110' />
        <span aria-hidden className='pointer-events-none absolute inset-y-0 -start-1/3 w-1/3 -skew-x-12 bg-gradient-to-r from-transparent via-white/20 to-transparent opacity-0 group-hover:animate-[referral-shine_1.1s_ease-out] group-hover:opacity-100' />

        <div className='relative flex flex-wrap items-center justify-between gap-4'>
          <div className='min-w-0'>
            <div className='flex items-center gap-1.5 text-xs font-medium uppercase tracking-widest text-white/80'>
              <Gift className='h-3.5 w-3.5' /> Your referral code
            </div>
            <button
              type='button'
              onClick={code.copy}
              aria-label={`Copy referral code ${partner.code}`}
              className='mt-1.5 block rounded-lg font-mono text-4xl font-bold tracking-[0.2em] transition-transform active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70'
            >
              {partner.code}
            </button>
            {partner.previous_codes.length > 0 && (
              <p className='mt-1.5 text-xs text-white/75'>
                Your old code{partner.previous_codes.length > 1 ? 's' : ''} {partner.previous_codes.join(', ')} still work{partner.previous_codes.length > 1 ? '' : 's'}.
              </p>
            )}
          </div>

          <div className='flex flex-wrap gap-2'>
            <Button type='button' variant='secondary' size='sm' onClick={code.copy} className='transition-transform active:scale-95'>
              <CopyIcon copied={code.copied} /> {code.copied ? 'Copied' : 'Copy code'}
            </Button>
            <Button type='button' variant='secondary' size='sm' onClick={link.copy} className='transition-transform active:scale-95'>
              {link.copied ? <Check className='h-4 w-4 animate-in zoom-in-50 duration-200' /> : <Link2 className='h-4 w-4' />}
              {link.copied ? 'Copied' : 'Copy link'}
            </Button>
            {partner.code_can_change && (
              <Button type='button' variant='ghost' size='sm' onClick={() => setEditing(true)} className='text-white hover:bg-white/15 hover:text-white'>
                <Pencil className='h-4 w-4' /> Customize
              </Button>
            )}
          </div>
        </div>
      </div>

      {editing && <CustomizeCodeDialog partner={partner} onClose={() => setEditing(false)} />}
    </>
  )
}

/** Points surfaces: a night-sky base lit by teal, violet and pink glows (an aurora), distinct from the orange referral code. */
export const POINTS_AURORA: React.CSSProperties = {
  backgroundColor: '#0b1020',
  backgroundImage: [
    'radial-gradient(120% 90% at 0% 0%, rgb(20 184 166 / 0.55) 0%, transparent 55%)',
    'radial-gradient(90% 80% at 100% 10%, rgb(139 92 246 / 0.55) 0%, transparent 55%)',
    'radial-gradient(80% 70% at 60% 125%, rgb(244 114 182 / 0.4) 0%, transparent 60%)',
  ].join(', '),
}

/** The partner's points balance as a tile, as an aurora so it reads apart from the orange referral code. */
export function PointsCard({ partner }: { partner: PartnerSummary }) {
  return (
    <Link
      href='/partners/points'
      aria-label={`${formatPoints(partner.points_balance)} points. Open points page`}
      style={POINTS_AURORA}
      className='group relative block overflow-hidden rounded-2xl ring-1 ring-white/10 p-5 text-white shadow-lg transition hover:brightness-110 animate-in fade-in slide-in-from-bottom-2 duration-500'
    >
      <span aria-hidden className='pointer-events-none absolute -end-10 -top-12 h-40 w-40 rounded-full bg-white/10 transition-transform duration-700 group-hover:scale-125' />
      <span aria-hidden className='pointer-events-none absolute -bottom-14 start-1/3 h-36 w-36 rounded-full bg-black/10 transition-transform duration-700 group-hover:scale-110' />
      <div className='relative flex items-center justify-between gap-4'>
        <div>
          <div className='flex items-center gap-1.5 text-xs font-medium uppercase tracking-widest text-white/80'>
            <Coins className='h-3.5 w-3.5' /> Your points
          </div>
          <div className='mt-1.5 text-4xl font-bold tabular-nums'>{formatPoints(partner.points_balance)}</div>
          <p className='mt-1 text-xs text-white/75'>{TIER_LABEL[partner.tier]} tier · tap to see activity and redeem</p>
        </div>
        <ArrowRight className='h-5 w-5 transition-transform group-hover:translate-x-1' />
      </div>
    </Link>
  )
}

function CustomizeCodeDialog({ partner, onClose }: { partner: PartnerSummary; onClose: () => void }) {
  const form = useForm({ code: partner.code })
  const value = form.data.code.toUpperCase().replace(/[^A-Z0-9]/g, '')
  const valid = /^[A-Z0-9]{4,20}$/.test(value) && value !== partner.code

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.transform((d) => ({ code: d.code.toUpperCase().replace(/[^A-Z0-9]/g, '') }))
    form.post('/partners/code', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <form onSubmit={submit} className='space-y-4'>
          <DialogHeader>
            <DialogTitle>Customize your code</DialogTitle>
            <DialogDescription>
              Pick something your audience will remember. You can only do this once, so choose carefully.
            </DialogDescription>
          </DialogHeader>

          <div className='flex gap-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-xs text-muted-foreground'>
            <TriangleAlert className='mt-0.5 h-4 w-4 shrink-0 text-amber-600' />
            <span>
              Links and codes you already shared ({partner.code}) keep working and keep crediting you. After this, only our team can change it.
            </span>
          </div>

          <div>
            <Input
              value={form.data.code}
              onChange={(e) => form.setData('code', e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 20))}
              maxLength={20}
              autoFocus
              autoComplete='off'
              spellCheck={false}
              aria-label='New referral code'
              className='h-12 text-center font-mono text-xl font-semibold tracking-[0.2em]'
            />
            <div className='mt-1.5 flex justify-between text-xs text-muted-foreground'>
              <span>4–20 letters and digits</span>
              <span className='tabular-nums'>{value.length}/20</span>
            </div>
            {form.errors.code && <p className='mt-1.5 text-sm text-red-600 animate-in fade-in slide-in-from-top-1'>{form.errors.code}</p>}
          </div>

          <DialogFooter>
            <Button type='button' variant='outline' onClick={onClose}>Cancel</Button>
            <Button type='submit' disabled={!valid || form.processing}>{form.processing ? 'Saving…' : 'Save code'}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/**
 * The code, floating in the lower-right corner like the stock alerts, so it
 * is one click away on every page and tab, the Referrals page included.
 */
export function FloatingReferral({ partner }: { partner: PartnerSummary }) {
  const code = useCopy(partner.code, 'Code copied')
  const link = useCopy(partner.referral_url, 'Link copied')
  const { hasRole } = usePermissions()

  if (!['approved', 'pending'].includes(partner.status)) return null

  return (
    <div className={cn('fixed end-4 z-40 flex flex-col items-end gap-2', hasRole('client') ? 'bottom-36' : 'bottom-4')}>
      <Link
        href='/partners/points'
        aria-label={`${formatPoints(partner.points_balance)} points. Open points page`}
        style={POINTS_AURORA}
        className='group relative flex items-center gap-2 overflow-hidden rounded-full ring-1 ring-white/10 py-1.5 pe-4 ps-1.5 text-white shadow-xl transition hover:brightness-110 active:scale-95 animate-in fade-in slide-in-from-right-6 zoom-in-95 duration-300'
      >
        <span aria-hidden className='pointer-events-none absolute -end-4 -top-5 h-10 w-10 rounded-full bg-white/10' />
        <span className='relative grid h-8 w-8 place-items-center rounded-full bg-white/15'>
          <Coins className='h-4 w-4 transition-transform group-hover:rotate-12 group-hover:scale-110' />
        </span>
        <span className='relative text-sm font-bold tabular-nums'>{formatPoints(partner.points_balance)}</span>
        <span className='relative text-[11px] font-medium uppercase tracking-wider text-white/80'>pts</span>
      </Link>
      <div className='relative flex items-center gap-1 overflow-hidden rounded-full bg-orange-600 py-1.5 pe-1.5 ps-4 text-white shadow-xl ring-1 ring-black/10 animate-in fade-in slide-in-from-right-6 zoom-in-95 duration-300'>
        <span aria-hidden className='pointer-events-none absolute -start-4 -top-5 h-10 w-10 rounded-full bg-white/10' />
        <Gift className='relative h-4 w-4 text-white/85' />
        <span className='relative ms-1 hidden text-[11px] font-medium uppercase tracking-wider text-white/80 sm:inline'>Your code</span>
        <button
          type='button'
          onClick={code.copy}
          aria-label={`Copy referral code ${partner.code}`}
          className='relative mx-1.5 rounded-md font-mono text-sm font-bold tracking-[0.18em] transition-transform active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70'
        >
          {partner.code}
        </button>
        <button
          type='button'
          onClick={code.copy}
          aria-label='Copy code'
          className='relative grid h-8 w-8 place-items-center rounded-full bg-white/15 transition hover:bg-white/25 active:scale-90'
        >
          <CopyIcon copied={code.copied} />
        </button>
        <button
          type='button'
          onClick={link.copy}
          aria-label='Copy referral link'
          className='relative grid h-8 w-8 place-items-center rounded-full bg-white/15 transition hover:bg-white/25 active:scale-90'
        >
          {link.copied ? <Check className='h-4 w-4 animate-in zoom-in-50 duration-200' /> : <Link2 className='h-4 w-4' />}
        </button>
      </div>
    </div>
  )
}

export function StatusBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    active: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    approved: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    paid: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    converted: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    pending: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    requested: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    new: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    contacted: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    inactive: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
    suspended: 'bg-red-500/15 text-red-700 dark:text-red-300',
    rejected: 'bg-red-500/15 text-red-700 dark:text-red-300',
    lost: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
  }
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${map[status] ?? map.inactive}`}>
      {status}
    </span>
  )
}
