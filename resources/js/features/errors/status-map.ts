/**
 * Copy for every HTTP status we render a full-page error for. Kept in one place
 * so the Laravel-rendered `Errors/Error` page and the standalone `/errors/*`
 * pages can never drift apart.
 */
export type ErrorStatus = 401 | 403 | 404 | 419 | 429 | 500 | 503

type ErrorCopy = {
  title: string
  description: string
  /** Whether retrying the same request could plausibly succeed. */
  retryable: boolean
}

export const ERROR_COPY: Record<ErrorStatus, ErrorCopy> = {
  401: {
    title: 'You need to sign in',
    description:
      'You are not signed in, or your session has ended. Sign in again to continue.',
    retryable: false,
  },
  403: {
    title: 'You do not have access to this page',
    description:
      "Your account doesn't have permission to view this resource. If you think this is a mistake, contact your administrator.",
    retryable: false,
  },
  404: {
    title: 'We could not find that page',
    description:
      'The page you are looking for does not exist, or it may have been moved or deleted.',
    retryable: false,
  },
  419: {
    title: 'Your session expired',
    description:
      'You were away for a while and this page went stale. Reload to pick up where you left off — you may need to sign in again.',
    retryable: true,
  },
  429: {
    title: 'Too many requests',
    description:
      'You have made too many requests in a short time. Wait a moment before trying again.',
    retryable: true,
  },
  500: {
    title: 'Something went wrong on our end',
    description:
      'We hit an unexpected error while handling your request. The team has been notified. Copy the details below if you need to report it.',
    retryable: true,
  },
  503: {
    title: 'We are down for maintenance',
    description:
      'The portal is temporarily unavailable while we perform scheduled maintenance. Please check back shortly.',
    retryable: true,
  },
}

export const DEFAULT_ERROR_COPY: ErrorCopy = {
  title: 'Something went wrong',
  description:
    'We hit an unexpected error. You can go back, retry, or copy the details below and send them to support.',
  retryable: true,
}

export function copyForStatus(status: number): ErrorCopy {
  return ERROR_COPY[status as ErrorStatus] ?? DEFAULT_ERROR_COPY
}
