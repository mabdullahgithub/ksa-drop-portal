import { Head, Link } from '@inertiajs/react'
import { CheckCircle2 } from 'lucide-react'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'

const BRAND = '#f26722'

const NEXT_STEPS = [
  'Our team reviews your business details.',
  'We email you as soon as a decision is made.',
  'Once approved, you sign in with the email and password you chose.',
]

export default function RegisterSubmitted({
  email,
  reference,
}: {
  email: string
  reference: string | null
}) {
  return (
    <ThemeProvider>
      <Head title='Request submitted' />
      <div className='relative flex min-h-svh flex-col items-center justify-center gap-8 bg-background px-6 py-12'>
        <div className='absolute right-6 top-6'>
          <ThemeSwitch />
        </div>
        <AuthLogo />

        <div className='w-full max-w-md rounded-xl border bg-card p-8 text-center'>
          <CheckCircle2 className='mx-auto h-14 w-14 text-green-600 dark:text-green-500' aria-hidden />
          <h1 className='mt-4 text-2xl font-semibold tracking-tight'>
            Your request has been submitted
          </h1>
          <p className='mt-2 text-sm text-muted-foreground'>
            Thank you for choosing KSA DROP. Your merchant account is now under review.
          </p>

          <dl className='mt-6 space-y-2 rounded-lg bg-muted/50 p-4 text-left text-sm'>
            {reference && (
              <div className='flex justify-between gap-4'>
                <dt className='text-muted-foreground'>Reference</dt>
                <dd className='font-mono font-semibold'>{reference}</dd>
              </div>
            )}
            <div className='flex justify-between gap-4'>
              <dt className='text-muted-foreground'>Email</dt>
              <dd className='min-w-0 truncate font-medium'>{email}</dd>
            </div>
          </dl>

          <div className='mt-6 text-left'>
            <h2 className='text-sm font-semibold'>What happens next</h2>
            <ol className='mt-3 space-y-2.5'>
              {NEXT_STEPS.map((text, index) => (
                <li key={text} className='flex gap-3 text-sm text-muted-foreground'>
                  <span
                    className='flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white'
                    style={{ backgroundColor: BRAND }}
                  >
                    {index + 1}
                  </span>
                  {text}
                </li>
              ))}
            </ol>
          </div>

          <Link
            href={route('login')}
            className='mt-8 flex h-12 w-full items-center justify-center rounded-lg text-sm font-semibold text-white shadow-sm transition-opacity hover:opacity-90'
            style={{ backgroundColor: BRAND }}
          >
            Back to sign in
          </Link>
        </div>
      </div>
    </ThemeProvider>
  )
}
