import { Head, Link, useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ThemeProvider } from '@/context/theme-provider'
import { AuthLogo } from '@/components/layout/auth-logo'

export default function VerifyEmail({ status }: { status?: string }) {
  const { post, processing } = useForm({})

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('verification.send'))
  }

  return (
    <ThemeProvider>
      <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
        <Head title='Email Verification' />
        <div className='flex w-full max-w-sm flex-col gap-6'>
          <div className='flex justify-center'>
            <AuthLogo />
          </div>
          <Card>
          <CardHeader className='text-center'>
            <CardTitle className='text-xl'>Verify Email</CardTitle>
            <CardDescription>
              Thanks for signing up! Please verify your email address by clicking
              the link we just emailed to you.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {status === 'verification-link-sent' && (
              <div className='mb-4 text-sm font-medium text-green-600'>
                A new verification link has been sent to your email address.
              </div>
            )}
            <form onSubmit={submit}>
              <div className='flex items-center justify-between'>
                <Button type='submit' disabled={processing}>
                  Resend Verification Email
                </Button>
                <Link
                  href={route('logout')}
                  method='post'
                  as='button'
                  className='text-sm text-muted-foreground underline hover:text-foreground'
                >
                  Log Out
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
