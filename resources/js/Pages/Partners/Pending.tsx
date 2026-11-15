import { Head, Link } from '@inertiajs/react'
import { Clock, XCircle, PauseCircle } from 'lucide-react'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { PARTNER_TYPE_LABEL, formatDate, type PartnerStatus, type PartnerType } from '@/features/partners/types'

type Props = {
  partner: { status: PartnerStatus; type: PartnerType; code: string; rejection_reason: string | null; created_at: string } | null
  isClient: boolean
}

/** Where anyone with the partner role but no approved partner row ends up. */
export default function Pending({ partner, isClient }: Props) {
  const status = partner?.status ?? 'pending'

  const copy = {
    pending: {
      icon: <Clock className='h-6 w-6' />,
      tone: 'bg-amber-500/10 text-amber-600',
      title: 'Application under review',
      body: `Thanks for applying${partner ? ` as ${PARTNER_TYPE_LABEL[partner.type].toLowerCase()}` : ''}. Our team reviews every application by hand, usually within two business days. We will email you as soon as it is decided.`,
    },
    rejected: {
      icon: <XCircle className='h-6 w-6' />,
      tone: 'bg-destructive/10 text-destructive',
      title: 'Application not approved',
      body: partner?.rejection_reason
        ? `Reason given: ${partner.rejection_reason}`
        : 'We were not able to approve your application this time. If anything has changed, contact us and we will take another look.',
    },
    suspended: {
      icon: <PauseCircle className='h-6 w-6' />,
      tone: 'bg-destructive/10 text-destructive',
      title: 'Partner account suspended',
      body: 'Your partner account is paused. Your points are kept. Contact our team to resolve this.',
    },
    approved: { icon: null, tone: '', title: '', body: '' },
  }[status]

  return (
    <ThemeProvider>
      <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
        <Head title={copy.title} />
        <div className='absolute right-6 top-6'>
          <ThemeSwitch />
        </div>
        <div className='flex w-full max-w-md flex-col gap-6'>
          <div className='flex justify-center'>
            <AuthLogo />
          </div>
          <Card>
            <CardHeader className='text-center'>
              <div className='mb-2 flex justify-center'>
                <span className={`flex h-12 w-12 items-center justify-center rounded-full ${copy.tone}`}>{copy.icon}</span>
              </div>
              <CardTitle className='text-xl'>{copy.title}</CardTitle>
              <CardDescription>{copy.body}</CardDescription>
            </CardHeader>
            <CardContent className='flex flex-col gap-3'>
              {partner && (
                <dl className='grid grid-cols-2 gap-y-1 rounded-lg bg-muted/60 p-3 text-sm'>
                  <dt className='text-muted-foreground'>Your code</dt>
                  <dd className='text-right font-mono font-semibold'>{partner.code}</dd>
                  <dt className='text-muted-foreground'>Applied</dt>
                  <dd className='text-right'>{formatDate(partner.created_at)}</dd>
                </dl>
              )}
              {status === 'pending' && (
                <Button asChild className='w-full'>
                  <Link href='/partners/dashboard'>Explore your partner portal</Link>
                </Button>
              )}
              {isClient && (
                <Button asChild className='w-full'>
                  <Link href='/portal'>Back to my portal</Link>
                </Button>
              )}
              <Button asChild variant='outline' className='w-full'>
                <a href='mailto:support@ksadrop.com?subject=Partner%20program'>Contact support</a>
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
