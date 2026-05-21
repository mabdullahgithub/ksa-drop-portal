import { router, usePage } from '@inertiajs/react'
import { ShieldCheck, X } from 'lucide-react'
import type { PageProps } from '@/types'

export function ImpersonateBanner() {
  const { auth } = usePage<PageProps>().props

  if (!auth.impersonating) return null

  return (
    <div className='fixed inset-x-0 top-0 z-[9999] flex h-8 items-center justify-between gap-4 bg-amber-400 px-4 text-amber-950'>
      <div className='flex items-center gap-1.5 text-xs font-medium'>
        <ShieldCheck className='h-3.5 w-3.5 shrink-0' />
        <span>
          Impersonating <strong>{auth.impersonating.client_name}</strong>
        </span>
      </div>
      <button
        onClick={() => router.post(route('impersonate.leave'))}
        className='flex items-center gap-1 rounded px-2 py-0.5 text-xs font-semibold hover:bg-amber-500 transition-colors'
      >
        <X className='h-3 w-3' />
        Exit
      </button>
    </div>
  )
}
