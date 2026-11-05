import { useEffect } from 'react'
import { Head, router } from '@inertiajs/react'
import { ErrorPage } from '@/features/errors/error-page'
import { copyForStatus } from '@/features/errors/status-map'
import { useDefaultRoute } from '@/hooks/use-default-route'

type Props = {
  status: number
  /** Log correlation id, safe to show in production. */
  reference?: string | null
  /** Server-supplied description that replaces the default copy for the status. */
  message?: string | null
  /** Server override for whether a "Try again" button makes sense. */
  retryable?: boolean
}

/**
 * Rendered by Laravel for every HTTP error status (see bootstrap/app.php), and
 * by the standalone /errors/* preview routes.
 *
 * Deliberately not named `Error` — importing it under that name would shadow the
 * global `Error` constructor in the consuming module.
 */
export default function ErrorStatusPage({
  status,
  reference,
  message,
  retryable: retryableOverride,
}: Props) {
  const copy = copyForStatus(status)
  const title = copy.title
  const description = message ?? copy.description
  const retryable = retryableOverride ?? copy.retryable
  const fallbackRoute = useDefaultRoute()

  // On a 403, don't dead-end the user: if they can view some other page, send
  // them there instead. Only show the 403 when there's nowhere to fall back to.
  // The /errors/* routes are intentional previews, so never redirect from them.
  const redirecting =
    status === 403 &&
    fallbackRoute !== null &&
    fallbackRoute !== window.location.pathname &&
    !window.location.pathname.startsWith('/errors/')

  useEffect(() => {
    if (redirecting) {
      router.visit(fallbackRoute as string, { replace: true })
    }
  }, [redirecting, fallbackRoute])

  if (redirecting) {
    return <Head title={`${status} - ${title}`} />
  }

  return (
    <>
      <Head title={`${status} - ${title}`} />
      <ErrorPage
        status={status}
        title={title}
        description={description}
        reference={reference}
        manageDocumentTitle={false}
        onRetry={retryable ? () => router.reload() : undefined}
      />
    </>
  )
}
