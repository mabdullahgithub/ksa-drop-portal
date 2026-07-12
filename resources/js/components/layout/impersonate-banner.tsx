import { useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { Eye, LogOut } from 'lucide-react'
import type { PageProps } from '@/types'

export function ImpersonateBanner() {
  const { auth } = usePage<PageProps>().props
  const [leaving, setLeaving] = useState(false)

  if (!auth.impersonating) return null

  const initials = auth.impersonating.client_name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase())
    .join('')

  return (
    <div className='fixed inset-x-0 top-0 z-[9999] h-9 border-b border-amber-500/30 bg-zinc-950 text-zinc-100 shadow-sm'>
      <div className='flex h-full items-center justify-between gap-4 px-4'>
        <div className='flex min-w-0 items-center gap-2.5'>
          <span className='flex items-center gap-1.5 rounded-full bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-300'>
            <Eye className='h-3 w-3 shrink-0' />
            <span className='hidden sm:inline'>Impersonation mode</span>
            <span className='sm:hidden'>Impersonating</span>
          </span>

          <span className='hidden h-4 w-px bg-white/10 sm:block' />

          <div className='flex min-w-0 items-center gap-2'>
            <span className='hidden h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-[10px] font-semibold text-zinc-200 sm:flex'>
              {initials}
            </span>
            <span className='truncate text-xs text-zinc-400'>
              Viewing as{' '}
              <span className='font-medium text-zinc-100'>
                {auth.impersonating.client_name}
              </span>
            </span>
          </div>
        </div>

        <button
          type='button'
          disabled={leaving}
          onClick={() => {
            setLeaving(true)
            router.post(
              route('impersonate.leave'),
              {},
              { onFinish: () => setLeaving(false) }
            )
          }}
          className='flex shrink-0 items-center gap-1.5 rounded-md border border-white/15 px-2.5 py-1 text-xs font-medium text-zinc-100 transition-colors hover:border-amber-400/50 hover:bg-amber-400/10 hover:text-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/60 disabled:cursor-not-allowed disabled:opacity-60'
        >
          <LogOut className='h-3.5 w-3.5' />
          {leaving ? 'Exiting…' : 'Exit impersonation'}
        </button>
      </div>
    </div>
  )
}
