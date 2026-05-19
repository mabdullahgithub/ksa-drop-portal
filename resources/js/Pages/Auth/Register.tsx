import { Head, Link, useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'

export default function Register() {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('register'), {
      onFinish: () => reset('password', 'password_confirmation'),
    })
  }

  return (
    <ThemeProvider>
      <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
        <Head title='Register' />
        <div className='absolute right-6 top-6'>
          <ThemeSwitch />
        </div>
        <div className='flex w-full max-w-sm flex-col gap-6'>
          <div className='flex justify-center'>
            <AuthLogo />
          </div>
        <Card>
          <CardHeader className='text-center'>
            <CardTitle className='text-xl'>Create an account</CardTitle>
            <CardDescription>Enter your details to get started</CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit}>
              <div className='grid gap-6'>
                <div className='grid gap-2'>
                  <Label htmlFor='name'>Name</Label>
                  <Input
                    id='name'
                    value={data.name}
                    autoComplete='name'
                    autoFocus
                    onChange={(e) => setData('name', e.target.value)}
                    className={cn(errors.name && 'border-destructive')}
                    required
                  />
                  {errors.name && (
                    <p className='text-sm text-destructive'>{errors.name}</p>
                  )}
                </div>
                <div className='grid gap-2'>
                  <Label htmlFor='email'>Email</Label>
                  <Input
                    id='email'
                    type='email'
                    value={data.email}
                    autoComplete='username'
                    onChange={(e) => setData('email', e.target.value)}
                    className={cn(errors.email && 'border-destructive')}
                    required
                  />
                  {errors.email && (
                    <p className='text-sm text-destructive'>{errors.email}</p>
                  )}
                </div>
                <div className='grid gap-2'>
                  <Label htmlFor='password'>Password</Label>
                  <Input
                    id='password'
                    type='password'
                    value={data.password}
                    autoComplete='new-password'
                    onChange={(e) => setData('password', e.target.value)}
                    className={cn(errors.password && 'border-destructive')}
                    required
                  />
                  {errors.password && (
                    <p className='text-sm text-destructive'>{errors.password}</p>
                  )}
                </div>
                <div className='grid gap-2'>
                  <Label htmlFor='password_confirmation'>Confirm Password</Label>
                  <Input
                    id='password_confirmation'
                    type='password'
                    value={data.password_confirmation}
                    autoComplete='new-password'
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    className={cn(errors.password_confirmation && 'border-destructive')}
                    required
                  />
                  {errors.password_confirmation && (
                    <p className='text-sm text-destructive'>{errors.password_confirmation}</p>
                  )}
                </div>
                <Button type='submit' className='w-full' disabled={processing}>
                  Register
                </Button>
              </div>
              <div className='mt-4 text-center text-sm'>
                Already have an account?{' '}
                <Link href={route('login')} className='underline underline-offset-4'>
                  Log in
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
