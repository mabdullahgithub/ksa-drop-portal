import { useEffect, useState } from 'react'
import axios from 'axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

/**
 * The websocket connection to Reverb, made once and only when this build has
 * its key (VITE_REVERB_APP_KEY) and the server says it broadcasts. Without
 * either, getEcho() is null and live chat polls instead, so a build or a
 * server without Reverb keeps working.
 */
let instance: Echo<'reverb'> | null | undefined

export function getEcho(enabled: boolean): Echo<'reverb'> | null {
  if (!enabled || typeof window === 'undefined') return null
  if (instance !== undefined) return instance

  const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined
  if (!key) return (instance = null)

  const scheme = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'https'
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? (scheme === 'https' ? 443 : 80))

  ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

  instance = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // Through axios, so the XSRF cookie and the session go with it like any
    // other request; the page has no CSRF meta tag.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    authorizer: ((channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: unknown) => void) => {
        axios
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((response) => callback(null, response.data))
          .catch((error) => callback(error, null))
      },
    })) as any,
  })

  return instance
}

type ConnectionState = 'connected' | 'connecting' | 'unavailable'

/**
 * Whether the live connection is up right now. Anything but `connected`
 * means the chat screens poll; when it comes back they fetch what they
 * missed.
 */
export function useEchoConnection(enabled: boolean): ConnectionState {
  const echo = getEcho(enabled)
  const [state, setState] = useState<ConnectionState>(() => read(echo))

  useEffect(() => {
    if (!echo) {
      setState('unavailable')
      return
    }

    const connection = echo.connector.pusher.connection
    const onChange = () => setState(read(echo))
    connection.bind('state_change', onChange)
    onChange()

    return () => {
      connection.unbind('state_change', onChange)
    }
  }, [echo])

  return state
}

function read(echo: Echo<'reverb'> | null): ConnectionState {
  if (!echo) return 'unavailable'
  const state = echo.connector.pusher.connection.state
  if (state === 'connected') return 'connected'
  if (state === 'connecting' || state === 'initialized') return 'connecting'
  return 'unavailable'
}
