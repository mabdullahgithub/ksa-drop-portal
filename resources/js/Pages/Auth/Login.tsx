import { Head, Link, useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PasswordInput } from '@/components/password-input'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'

const BRAND = '#f26722'
const BRAND_HOVER = '#d9591c'

export default function Login({
  status,
  canResetPassword,
}: {
  status?: string
  canResetPassword: boolean
}) {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    password: '',
    remember: false as boolean,
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('login'), {
      onFinish: () => reset('password'),
    })
  }

  return (
    <ThemeProvider>
      <Head title='Log in' />
      <div className='relative grid min-h-svh grid-cols-1 lg:grid-cols-[1.6fr_1fr]'>
        {/* Brand / illustration panel */}
        <div className='relative hidden flex-col bg-muted/60 lg:flex'>
          <div className='absolute left-8 top-7'>
            <AuthLogo />
          </div>
          <div className='flex flex-1 items-center justify-center p-10'>
            <video
              src='/images/login/drone-with-parcel.mp4'
              className='h-auto w-full max-w-2xl select-none object-contain'
              autoPlay
              loop
              muted
              playsInline
              aria-label='KSA Drop'
            />
          </div>
        </div>

        {/* Form panel */}
        <div className='relative flex flex-col bg-background'>
          {/* mobile logo */}
          <div className='flex items-center px-6 pt-6 lg:hidden'>
            <AuthLogo />
          </div>

          <div className='absolute right-6 top-6'>
            <ThemeSwitch />
          </div>

          <div className='flex flex-1 items-center justify-center px-6 py-12'>
            <div className='w-full max-w-sm'>
              <div className='mb-7'>
                <h1 className='text-2xl font-semibold tracking-tight'>
                  Welcome to KSA DROP! <span aria-hidden>👋</span>
                </h1>
                <p className='mt-1.5 text-sm text-muted-foreground'>
                  Please sign-in to your account and start the adventure
                </p>
              </div>

              {status && (
                <div className='mb-4 rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700'>
                  {status}
                </div>
              )}

              <form onSubmit={submit} className='space-y-5'>
                {/* Email */}
                <div className='group relative'>
                  <Label
                    htmlFor='email'
                    className='absolute -top-2 left-2.5 z-10 bg-background px-1 text-xs font-medium text-muted-foreground transition-colors group-focus-within:text-foreground'
                  >
                    Email ID <span style={{ color: BRAND }}>*</span>
                  </Label>
                  <Input
                    id='email'
                    type='email'
                    value={data.email}
                    autoComplete='username'
                    autoFocus
                    onChange={(e) => setData('email', e.target.value)}
                    className={cn(
                      'h-12 rounded-lg border-input px-3.5',
                      'focus-visible:border-2 focus-visible:border-foreground focus-visible:ring-0',
                      errors.email && 'border-destructive'
                    )}
                  />
                  {errors.email && (
                    <p className='mt-1.5 text-sm text-destructive'>{errors.email}</p>
                  )}
                </div>

                {/* Password */}
                <div>
                  <div className='group relative'>
                    <Label
                      htmlFor='password'
                      className='absolute -top-2 left-2.5 z-10 bg-background px-1 text-xs font-medium text-muted-foreground transition-colors group-focus-within:text-foreground'
                    >
                      Password <span style={{ color: BRAND }}>*</span>
                    </Label>
                    <PasswordInput
                      id='password'
                      value={data.password}
                      autoComplete='current-password'
                      onChange={(e) => setData('password', e.target.value)}
                      className={cn(
                        '[&>input]:h-12 [&>input]:rounded-lg [&>input]:border-input [&>input]:px-3.5',
                        '[&>input]:focus-visible:border-2 [&>input]:focus-visible:border-foreground [&>input]:focus-visible:ring-0',
                        errors.password && '[&>input]:border-destructive'
                      )}
                    />
                  </div>
                  <div className='mt-2 flex justify-end'>
                    {canResetPassword && (
                      <Link
                        href={route('password.request')}
                        className='text-sm font-medium hover:underline'
                        style={{ color: BRAND }}
                      >
                        Forgot Password
                      </Link>
                    )}
                  </div>
                  {errors.password && (
                    <p className='mt-1.5 text-sm text-destructive'>{errors.password}</p>
                  )}
                </div>

                <button
                  type='submit'
                  disabled={processing}
                  className='flex h-12 w-full items-center justify-center rounded-lg text-sm font-semibold text-white shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60'
                  style={{ backgroundColor: BRAND }}
                  onMouseEnter={(e) =>
                    (e.currentTarget.style.backgroundColor = BRAND_HOVER)
                  }
                  onMouseLeave={(e) =>
                    (e.currentTarget.style.backgroundColor = BRAND)
                  }
                >
                  {processing ? 'Signing in…' : 'Sign In'}
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </ThemeProvider>
  )
}
