import { useEffect, useRef, useState } from 'react'
import { router } from '@inertiajs/react'
import { BotAvatar } from 'bot-avatars'
import { Loader2 } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp'
import { unlockRecycleBin } from '@/hooks/useRecycleBin'

const PIN_LENGTH = 7

type RecycleBinLockProps = {
  open: boolean
  onUnlocked: () => void
  /** Shown when a session expires mid-use rather than on a fresh page open. */
  notice?: string | null
}

/**
 * PIN prompt for the recycle bin, as a modal over the page.
 *
 * Deliberately not dismissible by escape or a click outside: there is nothing
 * usable behind it, so closing it would just leave an empty screen. Leaving is
 * an explicit choice via the button.
 */
export function RecycleBinLock({ open, onUnlocked, notice }: RecycleBinLockProps) {
  const [pin, setPin] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  // Guards against the auto-submit firing twice for one completed PIN.
  const submittedFor = useRef<string | null>(null)

  // A fresh prompt (including one after an expiry) starts empty.
  useEffect(() => {
    if (open) {
      setPin('')
      setError(null)
      submittedFor.current = null
    }
  }, [open])

  const submit = async (value: string) => {
    if (submitting) return

    setSubmitting(true)
    setError(null)

    try {
      await unlockRecycleBin(value)
      onUnlocked()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Incorrect PIN.')
      setPin('')
      submittedFor.current = null
    } finally {
      setSubmitting(false)
    }
  }

  // Submit as soon as the last digit lands, so there is no extra click.
  useEffect(() => {
    if (pin.length === PIN_LENGTH && submittedFor.current !== pin) {
      submittedFor.current = pin
      void submit(pin)
    }
  }, [pin])

  /**
   * Return to whatever page the user opened the bin from.
   *
   * Inertia restores the previous page on popstate, so browser history is the
   * thing that actually knows where they came from -- a hardcoded destination
   * would send someone who arrived from Orders to the dashboard instead.
   */
  const goBack = () => {
    const from = window.location.href

    window.history.back()

    // Nothing to go back to when the page was loaded directly (pasted URL, or
    // a refresh landing here), in which case back() is a no-op and the URL is
    // unchanged. Fall back to the dashboard so the button is never dead.
    window.setTimeout(() => {
      if (window.location.href === from) {
        router.visit('/dashboard')
      }
    }, 300)
  }

  return (
    <Dialog open={open}>
      <DialogContent
        showCloseButton={false}
        onEscapeKeyDown={(event) => event.preventDefault()}
        onInteractOutside={(event) => event.preventDefault()}
        // Blurs the whole app behind the prompt, sidebar and header included.
        overlayClassName='backdrop-blur-sm'
        className='sm:max-w-sm'
      >
        <DialogHeader className='items-center text-center'>
          <div className='mb-2'>
            <BotAvatar type='ghost' size={56} state={submitting ? 'working' : 'default'} />
          </div>
          <DialogTitle>Recycle Bin is locked</DialogTitle>
          <DialogDescription className='sr-only'>
            Enter the PIN to unlock the recycle bin.
          </DialogDescription>
        </DialogHeader>

        <div className='flex flex-col items-center gap-4 py-2'>
          {notice && (
            <Alert>
              <AlertDescription>{notice}</AlertDescription>
            </Alert>
          )}

          <InputOTP
            maxLength={PIN_LENGTH}
            value={pin}
            onChange={setPin}
            disabled={submitting}
            autoFocus
            // A PIN is not a one-time code; stop password managers and the
            // browser from offering to remember or autofill it.
            autoComplete='off'
          >
            <InputOTPGroup>
              {Array.from({ length: PIN_LENGTH }).map((_, index) => (
                <InputOTPSlot key={index} index={index} />
              ))}
            </InputOTPGroup>
          </InputOTP>

          {error && <p className='text-destructive text-sm font-medium'>{error}</p>}

          {submitting && (
            <p className='text-muted-foreground flex items-center gap-2 text-sm'>
              <Loader2 className='h-3.5 w-3.5 animate-spin' />
              Checking…
            </p>
          )}
        </div>

        <DialogFooter className='sm:justify-between'>
          <Button variant='ghost' size='sm' onClick={goBack}>
            Go back
          </Button>
          <Button
            size='sm'
            disabled={pin.length !== PIN_LENGTH || submitting}
            onClick={() => submit(pin)}
          >
            Unlock
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
