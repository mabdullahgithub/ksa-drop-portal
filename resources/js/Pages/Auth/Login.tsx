import { Head, Link, useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'

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
      <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
        <Head title='Log in' />
        <div className='absolute right-6 top-6'>
          <ThemeSwitch />
        </div>
        <div className='flex w-full max-w-sm flex-col gap-6'>
          <div className='flex justify-center'>
            <AuthLogo />
          </div>
          <Card>
            <CardHeader className='text-center'>
              <CardTitle className='text-xl'>Welcome back</CardTitle>
              <CardDescription>Login to your account</CardDescription>
            </CardHeader>
            <CardContent>
              {status && (
                <div className='mb-4 text-sm font-medium text-green-600'>
                  {status}
                </div>
              )}
              <form onSubmit={submit}>
                <div className='grid gap-6'>
                  <div className='grid gap-2'>
                    <Label htmlFor='email'>Email</Label>
                    <Input
                      id='email'
                      type='email'
                      value={data.email}
                      autoComplete='username'
                      autoFocus
                      onChange={(e) => setData('email', e.target.value)}
                      className={cn(errors.email && 'border-destructive')}
                    />
                    {errors.email && (
                      <p className='text-sm text-destructive'>{errors.email}</p>
                    )}
                  </div>
                  <div className='grid gap-2'>
                    <div className='flex items-center'>
                      <Label htmlFor='password'>Password</Label>
                      {canResetPassword && (
                        <Link
                          href={route('password.request')}
                          className='ml-auto text-sm underline-offset-4 hover:underline'
                        >
                          Forgot your password?
                        </Link>
                      )}
                    </div>
                    <Input
                      id='password'
                      type='password'
                      value={data.password}
                      autoComplete='current-password'
                      onChange={(e) => setData('password', e.target.value)}
                      className={cn(errors.password && 'border-destructive')}
                    />
                    {errors.password && (
                      <p className='text-sm text-destructive'>{errors.password}</p>
                    )}
                  </div>
                  <div className='flex items-center space-x-2'>
                    <Checkbox
                      id='remember'
                      checked={data.remember}
                      onCheckedChange={(checked) =>
                        setData('remember', checked === true)
                      }
                    />
                    <Label htmlFor='remember' className='text-sm font-normal'>
                      Remember me
                    </Label>
                  </div>
                  <Button type='submit' className='w-full' disabled={processing}>
                    Log in
                  </Button>
                </div>
                <div className='mt-4 text-center text-sm'>
                  Don&apos;t have an account?{' '}
                  <Link href={route('register')} className='underline underline-offset-4'>
                    Sign up
                  </Link>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </ThemeProvider>
  )
}
