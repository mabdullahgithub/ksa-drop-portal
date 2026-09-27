import { Component, type ErrorInfo, type ReactNode } from 'react'
import { router } from '@inertiajs/react'
import { ErrorPage } from '@/features/errors/error-page'

type Props = {
  children: ReactNode
}

type State = {
  error: Error | null
}

/**
 * Catches render-time exceptions anywhere in the page tree.
 *
 * Without this, React 19 unmounts the whole tree when a component throws, which
 * leaves the user staring at a blank white page. Instead we show a friendly error
 * page, keep the app navigable, and leave the technical cause in the console.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  private stopListening?: () => void

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
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
    this.setState({ error: null })
  }

  render() {
    if (!this.state.error) return this.props.children

    // The error and component stack are in the console (componentDidCatch);
    // the page itself shows none of it.
    return (
      <ErrorPage
        title='Something went wrong on this page'
        description='This page failed to load. You can go back, retry, or copy the details below and send them to support.'
        // A hard reload, not router.reload(): the crashed tree may have left
        // stale client state behind, and a clean document is the only retry
        // guaranteed not to trip over it again.
        onRetry={() => window.location.reload()}
      />
    )
  }
}
