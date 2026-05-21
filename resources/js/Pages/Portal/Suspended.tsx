import { Head, Link, usePage } from '@inertiajs/react'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { PageProps } from '@/types'

export default function Suspended() {
  const { auth } = usePage<PageProps>().props
  const status = auth.client?.status

  const title = status === 'suspended' ? 'Account Suspended' : 'Account Inactive'
  const message =
    status === 'suspended'
      ? 'Your account has been suspended. Please contact our support team to resolve this issue.'
      : 'Your account is currently inactive. Please contact our support team to reactivate it.'

  return (
    <ThemeProvider>
      <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
        <Head title={title} />
        <div className='absolute right-6 top-6'>
          <ThemeSwitch />
        </div>
        <div className='flex w-full max-w-sm flex-col gap-6'>
          <div className='flex justify-center'>
            <AuthLogo />
          </div>
          <Card>
            <CardHeader className='text-center'>
              <div className='mb-2 flex justify-center'>
                <span className='flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10 text-destructive text-2xl'>
                  &#x26A0;
                </span>
              </div>
              <CardTitle className='text-xl'>{title}</CardTitle>
              <CardDescription>{message}</CardDescription>
            </CardHeader>
            <CardContent className='flex flex-col gap-3'>
              <Button asChild variant='outline' className='w-full'>
                <a href='mailto:support@ksadrop.com'>Contact Support</a>
              </Button>
              <Button asChild variant='ghost' className='w-full'>
                <Link href={route('logout')} method='post' as='button'>
                  Sign out
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </ThemeProvider>
  )
}
