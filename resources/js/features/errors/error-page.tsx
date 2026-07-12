import { useEffect, useState } from 'react'
import { Check, ChevronDown, Copy, Home, RefreshCw, Undo2 } from 'lucide-react'
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'

export type ErrorPageProps = {
  /** HTTP status or short code shown as the headline. */
  status?: number | string
  title: string
  description: React.ReactNode
  /**
   * Raw technical detail (exception message, component stack, …). Rendered in a
   * collapsible block and always included in the copied report.
   */
  detail?: string | null
  /** Correlation id from the server, so support can find the log line. */
  reference?: string | null
  /** Renders a "Try again" button. Omit for states retrying can't fix (403/404). */
  onRetry?: () => void
  /** Hide the full-screen chrome when embedded inside an existing layout. */
  minimal?: boolean
  /**
   * Set the document title directly. Pass false from Inertia pages, which own
   * the title via <Head> — otherwise the two race and Inertia wins with an
   * empty title, leaving a stray "- App Name" in the tab.
   */
  manageDocumentTitle?: boolean
  className?: string
}

/**
 * The single error surface for the whole app: Laravel's HTTP error responses,
 * the client-side ErrorBoundary, and the standalone /errors/* pages.
 *
 * Deliberately free of any @inertiajs/react import. This renders as an error
 * boundary's fallback, where Inertia's React context is not guaranteed to
 * exist — a fallback that throws takes the entire tree down with it and puts
 * the blank page right back. Plain `window` navigation only.
 */
export function ErrorPage({
  status,
  title,
  description,
  detail,
  reference,
  onRetry,
  minimal = false,
  manageDocumentTitle = true,
  className,
}: ErrorPageProps) {
  const [detailOpen, setDetailOpen] = useState(false)
  const { copied, copy } = useCopyToClipboard()

  useEffect(() => {
    if (!manageDocumentTitle || minimal) return

    // On a crash after a client-side navigation this is the title the user is
    // left with. On a crash during the very first load, Inertia's head manager
    // flushes afterwards and wins, leaving just the app name — acceptable, and
    // not worth racing it for.
    document.title = status ? `${status} - ${title}` : title
  }, [status, title, minimal, manageDocumentTitle])

  // A support-ready report: everything needed to reproduce, in one paste.
  const buildReport = () =>
    [
      `Status:    ${status ?? 'Client error'}`,
      `Message:   ${title}`,
      reference ? `Reference: ${reference}` : null,
      `URL:       ${window.location.href}`,
      `Time:      ${new Date().toISOString()}`,
      `Browser:   ${navigator.userAgent}`,
      detail ? `\n--- Details ---\n${detail}` : null,
    ]
      .filter(Boolean)
      .join('\n')

  return (
    <div
      className={cn(
        'flex w-full flex-col items-center justify-center px-6 py-12 text-center',
        minimal ? 'h-full' : 'min-h-svh',
        className
      )}
    >
      {status && (
        <h1 className='text-[7rem] leading-none font-bold tracking-tight'>
          {status}
        </h1>
      )}

      <h2 className='mt-4 text-xl font-semibold'>{title}</h2>

      <p className='text-muted-foreground mt-2 max-w-md text-sm'>
        {description}
      </p>

      {reference && (
        <p className='text-muted-foreground mt-3 font-mono text-xs'>
          Reference: {reference}
        </p>
      )}

      <div className='mt-8 flex flex-wrap items-center justify-center gap-3'>
        <Button variant='outline' onClick={() => window.history.back()}>
          <Undo2 />
          Go Back
        </Button>

        {onRetry && (
          <Button variant='outline' onClick={onRetry}>
            <RefreshCw />
            Try Again
          </Button>
        )}

        <Button onClick={() => window.location.assign('/')}>
          <Home />
          Back to Home
        </Button>

        <Button
          variant='ghost'
          onClick={() => copy(buildReport())}
          aria-label='Copy error details'
        >
          {copied ? <Check className='text-emerald-600' /> : <Copy />}
          {copied ? 'Copied' : 'Copy Details'}
        </Button>
      </div>

      {detail && (
        <Collapsible
          open={detailOpen}
          onOpenChange={setDetailOpen}
          className='mt-8 w-full max-w-2xl'
        >
          <CollapsibleTrigger asChild>
            <Button variant='ghost' size='sm' className='text-muted-foreground'>
              <ChevronDown
                className={cn(
                  'transition-transform',
                  detailOpen && 'rotate-180'
                )}
              />
              {detailOpen ? 'Hide' : 'Show'} technical details
            </Button>
          </CollapsibleTrigger>

          <CollapsibleContent>
            <pre className='bg-muted text-muted-foreground mt-3 max-h-72 overflow-auto rounded-md border p-4 text-left font-mono text-xs whitespace-pre-wrap'>
              {detail}
            </pre>
          </CollapsibleContent>
        </Collapsible>
      )}
    </div>
  )
}
