import { useEffect } from 'react'
import { Head, router } from '@inertiajs/react'
import { ErrorPage } from '@/features/errors/error-page'
import { copyForStatus } from '@/features/errors/status-map'
import { useDefaultRoute } from '@/hooks/use-default-route'

type Props = {
  status: number
  /** Exception message and trace — only sent by the server when debug is on. */
  detail?: string | null
  /** Log correlation id, safe to show in production. */
  reference?: string | null
}

/**
 * Rendered by Laravel for every HTTP error status (see bootstrap/app.php), and
 * by the standalone /errors/* preview routes.
 *
 * Deliberately not named `Error` — importing it under that name would shadow the
 * global `Error` constructor in the consuming module.
 */
export default function ErrorStatusPage({ status, detail, reference }: Props) {
  const { title, description, retryable } = copyForStatus(status)
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
        detail={detail}
        reference={reference}
        manageDocumentTitle={false}
        onRetry={retryable ? () => router.reload() : undefined}
      />
    </>
  )
}
