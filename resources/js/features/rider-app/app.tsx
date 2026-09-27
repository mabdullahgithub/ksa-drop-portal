import { useEffect, useState } from 'react'
import { Toaster } from 'sonner'
import { I18nProvider } from './i18n'
import { ActivateScreen } from './screens/activate-screen'
import { AppShell } from './screens/app-shell'
import { Onboarding, onboardingSeen } from './screens/onboarding'
import { SignInScreen } from './screens/sign-in-screen'
import type { RiderBoot } from './types'

export function RiderApp({ boot }: { boot: RiderBoot }) {
  const [mode, setMode] = useState(boot.mode)
  const [reason, setReason] = useState<string | null>(boot.reason ?? null)
  // First open on this phone: the welcome and the guide, once. Not on the
  // activation page — in a browser tab that only helps the rider install.
  const [welcome, setWelcome] = useState(() => boot.mode !== 'activate' && !onboardingSeen())

  // Suspended, signed out by the admin, or signed in on another phone.
  useEffect(() => {
    const onSignedOut = (event: Event) => {
      setReason((event as CustomEvent<string>).detail ?? null)
      setMode('sign_in')
    }
    window.addEventListener('rider:signed-out', onSignedOut)
    return () => window.removeEventListener('rider:signed-out', onSignedOut)
  }, [])

  return (
    <I18nProvider>
      {welcome ? (
        <Onboarding onDone={() => setWelcome(false)} />
      ) : (
        <>
          {mode === 'activate' && boot.token && <ActivateScreen token={boot.token} name={boot.rider?.name} />}
          {mode === 'sign_in' && <SignInScreen reason={reason} />}
          {mode === 'app' && <AppShell />}
        </>
      )}
      <Toaster position='top-center' richColors closeButton={false} toastOptions={{ className: 'text-sm' }} />
    </I18nProvider>
  )
}
