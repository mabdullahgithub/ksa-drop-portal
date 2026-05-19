import { Head, useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ThemeProvider } from '@/context/theme-provider'
import { AuthLogo } from '@/components/layout/auth-logo'

export default function ConfirmPassword() {
  const { data, setData, post, processing, errors, reset } = useForm({
    password: '',
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('password.confirm'), {
      onFinish: () => reset('password'),
    })
  }

  return (
    <ThemeProvider>
      <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
        <Head title='Confirm Password' />
        <div className='flex w-full max-w-sm flex-col gap-6'>
          <div className='flex justify-center'>
            <AuthLogo />
          </div>
          <Card>
          <CardHeader className='text-center'>
            <CardTitle className='text-xl'>Confirm Password</CardTitle>
            <CardDescription>
              This is a secure area. Please confirm your password before continuing.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit}>
              <div className='grid gap-6'>
                <div className='grid gap-2'>
                  <Label htmlFor='password'>Password</Label>
                  <Input
                    id='password'
                    type='password'
                    value={data.password}
                    autoFocus
                    onChange={(e) => setData('password', e.target.value)}
                    className={cn(errors.password && 'border-destructive')}
                  />
                  {errors.password && (
                    <p className='text-sm text-destructive'>{errors.password}</p>
                  )}
                </div>
                <Button type='submit' className='w-full' disabled={processing}>
                  Confirm
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
