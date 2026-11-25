import { Head, Link } from '@inertiajs/react'
import { ExternalLink } from 'lucide-react'
import { SuccessCheck } from '@/components/auth/success-check'
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
  shopifyAppUrl,
}: {
  email: string
  reference: string | null
  shopifyAppUrl: string
}) {
  return (
    <ThemeProvider>
      <Head title='Request submitted' />
      {/* One screen: side by side from `md` up, so nothing needs scrolling. The
          overflow is only a safety net for very small screens: `m-auto` centres
          without ever pushing the top out of reach. */}
      <div className='relative flex h-svh overflow-y-auto bg-background px-4 py-4 sm:px-6'>
        <div className='absolute right-4 top-4 sm:right-6'>
          <ThemeSwitch />
        </div>

        <div className='m-auto flex w-full max-w-md flex-col items-center gap-4 md:max-w-3xl md:gap-6'>
          <AuthLogo className='h-9 md:h-12' />

          <div className='grid w-full gap-4 rounded-xl border bg-card p-5 md:grid-cols-2 md:gap-8 md:p-8'>
            <div className='flex flex-col justify-center text-center'>
              <SuccessCheck className='mx-auto h-20 w-20 text-green-600 md:h-28 md:w-28 dark:text-green-500' />
              <h1 className='mt-2 text-xl font-semibold tracking-tight md:mt-4 md:text-2xl'>
                Your request has been submitted
              </h1>
              <p className='mt-1.5 text-sm text-muted-foreground'>
                Thank you for choosing KSA DROP. Your merchant account is now under review.
              </p>

              <dl className='mt-3 space-y-1.5 rounded-lg bg-muted/50 p-3 text-left text-sm md:mt-5 md:p-4'>
                {reference && (
                  <div className='flex justify-between gap-4'>
                    <dt className='text-muted-foreground'>Reference</dt>
                    <dd className='font-mono font-semibold'>{reference}</dd>
                  </div>
                )}
                <div className='flex justify-between gap-4'>
                  <dt className='text-muted-foreground'>Email</dt>
                  <dd className='min-w-0 truncate font-medium' title={email}>
                    {email}
                  </dd>
                </div>
              </dl>
            </div>

            <div className='flex flex-col gap-3 border-t pt-4 md:gap-4 md:border-t-0 md:border-l md:pt-0 md:pl-8'>
              <div>
                <h2 className='text-sm font-semibold'>What happens next</h2>
                <ol className='mt-2 space-y-2'>
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

              <div className='overflow-hidden rounded-lg border'>
                {/* The badge's lettering is white, so it sits on dark in both themes. */}
                <div className='bg-[#0b0f19] px-3 py-2.5'>
                  <img
                    src='/images/integrations/shopify-partner.png'
                    alt='Shopify Partner'
                    width={597}
                    height={162}
                    className='h-8 w-auto'
                  />
                </div>
                <div className='border-t p-3'>
                  <h2 className='text-sm font-semibold'>We are a Shopify Partner</h2>
                  <p className='mt-1 text-sm text-muted-foreground'>
                    KSA DROP has its own Shopify app. Use KSADrop Portal to connect your store once
                    your account is approved.
                  </p>
                  <a
                    href={shopifyAppUrl}
                    target='_blank'
                    rel='noopener noreferrer'
                    className='mt-2 inline-flex items-center gap-1.5 text-sm font-medium hover:underline'
                    style={{ color: BRAND }}
                  >
                    View our app on Shopify
                    <ExternalLink className='h-4 w-4' aria-hidden />
                  </a>
                </div>
              </div>

              <Link
                href={route('login')}
                className='mt-auto flex h-11 w-full shrink-0 items-center justify-center rounded-lg text-sm font-semibold text-white shadow-sm transition-opacity hover:opacity-90'
                style={{ backgroundColor: BRAND }}
              >
                Back to sign in
              </Link>
            </div>
          </div>
        </div>
      </div>
    </ThemeProvider>
  )
}
