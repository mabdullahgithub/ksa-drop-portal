import { Head, useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'

export default function ForgotPassword({ status }: { status?: string }) {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('password.email'))
  }

  return (
    <ThemeProvider>
      <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
        <Head title='Forgot Password' />
        <div className='absolute right-6 top-6'>
          <ThemeSwitch />
        </div>
        <div className='flex w-full max-w-sm flex-col gap-6'>
          <div className='flex justify-center'>
            <AuthLogo />
          </div>
          <Card>
            <CardHeader className='text-center'>
              <CardTitle className='text-xl'>Forgot Password</CardTitle>
              <CardDescription>
                Enter your email and we will send you a password reset link.
              </CardDescription>
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
                      autoFocus
                      onChange={(e) => setData('email', e.target.value)}
                      className={cn(errors.email && 'border-destructive')}
                    />
                    {errors.email && (
                      <p className='text-sm text-destructive'>{errors.email}</p>
                    )}
                  </div>
                  <Button type='submit' className='w-full' disabled={processing}>
                    Email Password Reset Link
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </ThemeProvider>
  )
}
