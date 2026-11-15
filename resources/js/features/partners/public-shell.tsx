import { useEffect, useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { Moon, Sun } from 'lucide-react'

/**
 * The frame around the public partner pages: same light-first, narrow,
 * typographic style as /pricing, so they read as one site.
 */
export function PublicShell({
  title,
  description,
  children,
  wide = false,
}: {
  title: string
  description: string
  children: React.ReactNode
  wide?: boolean
}) {
  const { url } = usePage()
  const onApply = url.startsWith('/partners/apply')
  const [isDark, setIsDark] = useState(true)

  useEffect(() => {
    document.documentElement.classList.add('dark')
  }, [])

  const toggleDark = () => {
    const next = !isDark
    setIsDark(next)
    document.documentElement.classList.toggle('dark', next)
  }

  const width = wide ? 'max-w-5xl' : 'max-w-3xl'

  return (
    <>
      <Head>
        <title>{`${title} — KSA Drop`}</title>
        <meta name='description' content={description} />
      </Head>

      <div className='relative min-h-screen overflow-x-clip bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100'>
        {/* Sticky glass bar: content scrolls and blurs underneath it. */}
        <header className='sticky top-0 z-50 border-b border-white/40 bg-white/60 shadow-[0_1px_24px_-12px_rgba(15,23,42,0.25)] backdrop-blur-xl backdrop-saturate-150 supports-[not(backdrop-filter)]:bg-white/95 dark:border-white/10 dark:bg-slate-950/50 dark:supports-[not(backdrop-filter)]:bg-slate-950/95'>
        <div className={`mx-auto flex ${width} items-center justify-between px-6 py-3`}>
          <a href='/partners' className='flex items-center'>
            {/* The partner mark is dark on transparent; a white plate keeps it legible in dark mode. */}
            <img
              src='/images/partners/ksa-drop-partner-badge.png'
              alt='KSA Drop Partner'
              className='h-8 w-auto rounded-md dark:bg-white dark:px-2 dark:py-0.5'
            />
          </a>
          <nav className='flex items-center gap-3 sm:gap-5'>
            <Link
              href='/login'
              className='text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'
            >
              Log in
            </Link>
            {!onApply && (
              <Link
                href='/partners/apply'
                className='inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200'
              >
                Become a partner
              </Link>
            )}
            <button
              onClick={toggleDark}
              aria-label='Toggle dark mode'
              className='text-slate-400 transition-colors hover:text-slate-700 dark:hover:text-slate-200'
            >
              {isDark ? <Sun className='h-4 w-4' /> : <Moon className='h-4 w-4' />}
            </button>
          </nav>
        </div>
        </header>

        <main className={`relative z-10 mx-auto ${width} px-6`}>{children}</main>

        <footer className={`relative z-10 mx-auto ${width} px-6 pb-12 pt-16`}>
          <div className='flex flex-col gap-3 border-t border-slate-200 pt-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:text-slate-400'>
            <p>© {new Date().getFullYear()} KSA Drop. Fulfillment, delivery and cash on delivery across Saudi Arabia.</p>
            <div className='flex gap-5'>
              <a href='/pricing' className='hover:text-slate-900 dark:hover:text-slate-100'>Pricing</a>
              <a href='/track' className='hover:text-slate-900 dark:hover:text-slate-100'>Track a shipment</a>
              <a href='mailto:support@ksadrop.com' className='hover:text-slate-900 dark:hover:text-slate-100'>Support</a>
            </div>
          </div>
        </footer>
      </div>
    </>
  )
}

export const btnPrimary =
  'inline-flex items-center justify-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-700 disabled:opacity-60 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200'

export const btnSecondary =
  'inline-flex items-center justify-center gap-2 rounded-full border border-slate-300 px-6 py-3 text-sm font-medium text-slate-700 transition-colors hover:border-slate-900 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-300 dark:hover:text-white'

export const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition-colors placeholder:text-slate-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100'

export const labelClass = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300'

export function FieldError({ message }: { message?: string }) {
  return message ? <p className='mt-1 text-xs text-red-600 dark:text-red-400'>{message}</p> : null
}
