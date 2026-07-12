import { Head, router } from '@inertiajs/react'
import { ErrorPage } from '@/features/errors/error-page'
import { copyForStatus } from '@/features/errors/status-map'

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
