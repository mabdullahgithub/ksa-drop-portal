import axios from 'axios'

const LOCKED_STATUS = 423

/**
 * The conversation endpoints answer 423 until the PIN has been entered, and
 * again once the inbox has sat idle too long. Told apart from an ordinary
 * failure so the inbox can drop back to the PIN prompt instead of an error.
 */
export const isWhatsAppLocked = (err: unknown): boolean =>
  axios.isAxiosError(err) && err.response?.status === LOCKED_STATUS

/** Exchange the PIN for an unlocked session. The PIN is only ever checked server-side. */
export async function unlockWhatsApp(pin: string): Promise<void> {
  try {
    await axios.post('/api/whatsapp/unlock', { pin })
  } catch (err) {
    // The prompt shows the message as-is; axios's own would read
    // "Request failed with status code 422".
    const message = axios.isAxiosError(err) ? err.response?.data?.message : null
    throw new Error(message || 'Incorrect PIN.')
  }
}

/** Re-lock on the way out, so the next visit prompts again. */
export async function lockWhatsApp(): Promise<void> {
  try {
    await axios.post('/api/whatsapp/lock')
  } catch {
    // Leaving the page should never surface an error; idle expiry re-locks anyway.
  }
}
