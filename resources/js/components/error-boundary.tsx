import { Component, type ErrorInfo, type ReactNode } from 'react'
import { router } from '@inertiajs/react'
import { ErrorPage } from '@/features/errors/error-page'

type Props = {
  children: ReactNode
}

type State = {
  error: Error | null
  componentStack: string | null
}

/**
 * Catches render-time exceptions anywhere in the page tree.
 *
 * Without this, React 19 unmounts the whole tree when a component throws, which
 * leaves the user staring at a blank white page with the real cause buried in
 * the console. Instead we show the error, keep the app navigable, and let the
 * user copy a support-ready report.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null, componentStack: null }

  private stopListening?: () => void

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    this.setState({ componentStack: info.componentStack ?? null })

    // Keep the console trace — this boundary hides the red screen, not the cause.
    console.error('[ErrorBoundary]', error, info.componentStack)
  }

  componentDidMount() {
    // A failed page left the boundary tripped; navigating away must clear it,
    // otherwise "Go Back" swaps the Inertia page underneath a still-broken tree.
    this.stopListening = router.on('navigate', () => {
      if (this.state.error) this.reset()
    })
  }

  componentWillUnmount() {
    this.stopListening?.()
  }

  reset = () => {
    this.setState({ error: null, componentStack: null })
  }

  render() {
    const { error, componentStack } = this.state

    if (!error) return this.props.children

    // error.stack already opens with "Name: message", so only fall back to
    // composing that line ourselves when there is no stack to show.
    const detail = [
      error.stack || `${error.name}: ${error.message}`,
      componentStack ? `--- Component stack ---${componentStack}` : null,
    ]
      .filter(Boolean)
      .join('\n\n')

    return (
      <ErrorPage
        title='Something went wrong on this page'
        description='This page failed to load. You can go back, retry, or copy the details below and send them to support.'
        detail={detail}
        // A hard reload, not router.reload(): the crashed tree may have left
        // stale client state behind, and a clean document is the only retry
        // guaranteed not to trip over it again.
        onRetry={() => window.location.reload()}
      />
    )
  }
}
