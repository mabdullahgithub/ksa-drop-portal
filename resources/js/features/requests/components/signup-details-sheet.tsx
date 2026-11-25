import { Link } from '@inertiajs/react'
import { format, parseISO } from 'date-fns'
import { toast } from 'sonner'
import { Check, Copy, ExternalLink, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import type { SignupRequest } from '@/hooks/useInventoryRequests'
import { SIGNUP_SERVICES, SignupStatusBadge } from './signup-parts'

type Props = {
  /** The signup shown; null keeps the sheet closed. */
  signup: SignupRequest | null
  onClose: () => void
  canOpenClient: boolean
  /** Left out when the person may not take that decision. */
  onApprove?: (signup: SignupRequest) => void
  onReject?: (signup: SignupRequest) => void
}

const when = (value: string | null) => (value ? format(parseISO(value), 'd MMM yyyy, p') : null)

const copy = async (value: string) => {
  try {
    await navigator.clipboard.writeText(value)
    toast.success('Copied')
  } catch {
    toast.error('Could not copy')
  }
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section>
      <h3 className='mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase'>
        {title}
      </h3>
      <dl className='divide-y rounded-lg border'>{children}</dl>
    </section>
  )
}

function Row({
  label,
  value,
  href,
  copyable,
}: {
  label: string
  value: string | null | undefined
  href?: string
  copyable?: boolean
}) {
  return (
    <div className='flex items-start justify-between gap-4 px-3 py-2.5 text-sm'>
      <dt className='shrink-0 text-muted-foreground'>{label}</dt>
      <dd className='flex min-w-0 items-start gap-2 text-right font-medium'>
        {value && href ? (
          <a href={href} className='break-all hover:underline'>
            {value}
          </a>
        ) : (
          <span className='break-words [overflow-wrap:anywhere]'>{value || '—'}</span>
        )}
        {value && copyable && (
          <button
            type='button'
            onClick={() => copy(value)}
            className='mt-0.5 shrink-0 text-muted-foreground hover:text-foreground'
            aria-label={`Copy ${label.toLowerCase()}`}
          >
            <Copy className='h-3.5 w-3.5' />
          </button>
        )}
      </dd>
    </div>
  )
}

/** Everything about one signup, with the decision at the bottom. */
export function SignupDetailsSheet({ signup, onClose, canOpenClient, onApprove, onReject }: Props) {
  const pending = signup?.status === 'pending'

  return (
    <Sheet open={!!signup} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className='w-full gap-0 sm:max-w-md'>
        {signup && (
          <>
            <SheetHeader className='border-b pe-12'>
              <SheetTitle className='[overflow-wrap:anywhere]'>{signup.company_name}</SheetTitle>
              <SheetDescription asChild>
                <div className='flex flex-wrap items-center gap-2'>
                  <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold text-foreground'>
                    {signup.reference}
                  </code>
                  <SignupStatusBadge status={signup.status} />
                </div>
              </SheetDescription>
            </SheetHeader>

            <div className='flex-1 space-y-5 overflow-y-auto p-4'>
              <Section title='Business'>
                <Row label='Company / store' value={signup.company_name} />
                <Row
                  label='Service'
                  value={signup.client_types.map((t) => SIGNUP_SERVICES[t] ?? t).join(', ')}
                />
                <Row label='City' value={signup.city} />
                <Row label='Country' value={signup.country} />
              </Section>

              <Section title='Contact'>
                <Row label='Full name' value={signup.contact_person} />
                <Row
                  label='Email'
                  value={signup.email}
                  href={signup.email ? `mailto:${signup.email}` : undefined}
                  copyable
                />
                <Row
                  label='Phone'
                  value={signup.phone}
                  href={signup.phone ? `tel:${signup.phone}` : undefined}
                  copyable
                />
              </Section>

              <Section title='Request'>
                <Row label='Submitted' value={when(signup.signed_up_at)} />
                {!pending && (
                  <>
                    <Row label='Reviewed' value={when(signup.reviewed_at)} />
                    <Row label='Reviewed by' value={signup.reviewed_by} />
                  </>
                )}
              </Section>

              {signup.status === 'rejected' && (
                <section className='rounded-lg border border-red-200 bg-red-50 p-3 text-sm dark:border-red-800 dark:bg-red-900/20'>
                  <h3 className='text-xs font-semibold tracking-wide text-red-700 uppercase dark:text-red-400'>
                    Rejection reason
                  </h3>
                  <p className='mt-1 whitespace-pre-wrap text-red-800 [overflow-wrap:anywhere] dark:text-red-300'>
                    {signup.rejection_reason || 'No reason was given.'}
                  </p>
                </section>
              )}

              {canOpenClient && (
                <Link
                  href={`/client/${signup.id}`}
                  className='inline-flex items-center gap-1.5 text-sm font-medium hover:underline'
                >
                  <ExternalLink className='h-4 w-4' />
                  Open client page (charges and settings)
                </Link>
              )}
            </div>

            {pending && (onApprove || onReject) && (
              <SheetFooter className='flex-row border-t'>
                {onReject && (
                  <Button variant='outline' className='flex-1' onClick={() => onReject(signup)}>
                    <X className='mr-1.5 h-4 w-4' />
                    Reject
                  </Button>
                )}
                {onApprove && (
                  <Button className='flex-1' onClick={() => onApprove(signup)}>
                    <Check className='mr-1.5 h-4 w-4' />
                    Approve
                  </Button>
                )}
              </SheetFooter>
            )}
          </>
        )}
      </SheetContent>
    </Sheet>
  )
}
