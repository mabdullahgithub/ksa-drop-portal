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

export default function ResetPassword({
  token,
  email,
}: {
  token: string
  email: string
}) {
  const { data, setData, post, processing, errors, reset } = useForm({
    token: token,
    email: email,
    password: '',
    password_confirmation: '',
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('password.store'), {
      onFinish: () => reset('password', 'password_confirmation'),
    })
  }

  return (
    <ThemeProvider>
      <Head title='Reset Password' />
      <div className='relative grid min-h-svh grid-cols-1 lg:grid-cols-[1.6fr_1fr]'>
        {/* Brand / illustration panel */}
        <div className='relative hidden flex-col bg-muted/60 lg:flex'>
          <div className='absolute left-8 top-7'>
            <AuthLogo />
          </div>
          <div className='flex flex-1 items-center justify-center p-10'>
            <img
              src='/images/login/3d-casual-life-joyful-man-holding-laptop-celebrating-success.png'
              className='h-auto w-full max-w-md select-none object-contain'
              alt='KSA Drop'
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
                  Reset Password <span aria-hidden>🔑</span>
                </h1>
                <p className='mt-1.5 text-sm text-muted-foreground'>
                  Choose a new password for your account
                </p>
              </div>

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
                    autoComplete='new-password'
                    autoFocus
                    onChange={(e) => setData('password', e.target.value)}
                    className={cn(
                      '[&>input]:h-12 [&>input]:rounded-lg [&>input]:border-input [&>input]:px-3.5',
                      '[&>input]:focus-visible:border-2 [&>input]:focus-visible:border-foreground [&>input]:focus-visible:ring-0',
                      errors.password && '[&>input]:border-destructive'
                    )}
                  />
                  {errors.password && (
                    <p className='mt-1.5 text-sm text-destructive'>{errors.password}</p>
                  )}
                </div>

                {/* Confirm Password */}
                <div className='group relative'>
                  <Label
                    htmlFor='password_confirmation'
                    className='absolute -top-2 left-2.5 z-10 bg-background px-1 text-xs font-medium text-muted-foreground transition-colors group-focus-within:text-foreground'
                  >
                    Confirm Password <span style={{ color: BRAND }}>*</span>
                  </Label>
                  <PasswordInput
                    id='password_confirmation'
                    value={data.password_confirmation}
                    autoComplete='new-password'
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    className={cn(
                      '[&>input]:h-12 [&>input]:rounded-lg [&>input]:border-input [&>input]:px-3.5',
                      '[&>input]:focus-visible:border-2 [&>input]:focus-visible:border-foreground [&>input]:focus-visible:ring-0',
                      errors.password_confirmation && '[&>input]:border-destructive'
                    )}
                  />
                  {errors.password_confirmation && (
                    <p className='mt-1.5 text-sm text-destructive'>
                      {errors.password_confirmation}
                    </p>
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
                  {processing ? 'Resetting…' : 'Reset Password'}
                </button>

                <div className='text-center'>
                  <Link
                    href={route('login')}
                    className='text-sm font-medium hover:underline'
                    style={{ color: BRAND }}
                  >
                    Back to Sign In
                  </Link>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </ThemeProvider>
  )
}
