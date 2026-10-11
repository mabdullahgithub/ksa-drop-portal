import { usePage } from '@inertiajs/react'

/** The screens that can sit behind the PIN. */
export type LockedScreen = 'recycle_bin' | 'whatsapp' | 'requests'

/**
 * Whether a screen asks for the PIN. Each has a switch in .env
 * (LOCK_RECYCLE_BIN, LOCK_WHATSAPP, LOCK_REQUESTS); the server enforces it,
 * this only tells the page whether to put up the prompt.
 */
export function useScreenLock(screen: LockedScreen): boolean {
  const { locks } = usePage().props as { locks?: Partial<Record<LockedScreen, boolean>> | null }

  // Not told otherwise, a screen is locked.
  return locks?.[screen] ?? true
}
