import { Camera, Events, Off, On } from '#nativephp'

/**
 * The phone's own camera, for proof and profile photos. A web view can't
 * open a file input, so the screens ask the host for a photo instead
 * (lib/host.ts, takePhoto).
 *
 * NativePHP saves the photo on the phone and reports where; the app's own
 * route (routes/web.php, /captured-photo) hands the web view the bytes.
 */

type Taken = { path: string; id?: string | null }

/** Resolves with the photo, or null if the rider backed out. Rejects when the camera is blocked. */
export function takePhoto(): Promise<Blob | null> {
  return new Promise((resolve, reject) => {
    const id = `photo-${Date.now()}`

    const done = () => {
      Off(Events.Camera.PhotoTaken, onTaken)
      Off(Events.Camera.PhotoCancelled, onCancelled)
      Off(Events.Camera.PermissionDenied, onDenied)
    }

    const onTaken = (payload: Taken) => {
      // An answer to an earlier, abandoned request.
      if (payload.id && payload.id !== id) return
      done()
      fetch(`/captured-photo?path=${encodeURIComponent(payload.path)}`)
        .then((response) => (response.ok ? response.blob() : Promise.reject(new Error('photo unreadable'))))
        .then(resolve, reject)
    }
    const onCancelled = () => {
      done()
      resolve(null)
    }
    const onDenied = () => {
      done()
      reject(new Error('camera blocked'))
    }

    On(Events.Camera.PhotoTaken, onTaken)
    On(Events.Camera.PhotoCancelled, onCancelled)
    On(Events.Camera.PermissionDenied, onDenied)

    Promise.resolve(Camera.getPhoto().id(id)).catch((error) => {
      done()
      reject(error)
    })
  })
}
