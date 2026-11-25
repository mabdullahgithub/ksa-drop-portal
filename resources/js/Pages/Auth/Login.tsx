import { Head, Link, useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { cn } from '@/lib/utils'
import { authSplitLayout } from '@/components/auth/auth-split-layout'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PasswordInput } from '@/components/password-input'

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
    <>
      <Head title='Log in' />
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
                errors.email && 'border-destructive',
              )}
            />
            {errors.email && <p className='mt-1.5 text-sm text-destructive'>{errors.email}</p>}
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
                  errors.password && '[&>input]:border-destructive',
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
            onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = BRAND_HOVER)}
            onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = BRAND)}
          >
            {processing ? 'Signing in…' : 'Sign In'}
          </button>

          <p className='text-center text-sm text-muted-foreground'>
            New to KSA DROP?{' '}
            <Link
              href={route('register')}
              prefetch
              className='font-medium hover:underline'
              style={{ color: BRAND }}
            >
              Create an account
            </Link>
          </p>
        </form>
      </div>
    </>
  )
}

Login.layout = authSplitLayout
