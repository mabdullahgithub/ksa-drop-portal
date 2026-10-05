import { useEffect, useRef, useState } from 'react'
import { createDetector, normalizeScannedCode, type Detector } from './barcode'
import { vibrate } from './device'

/** Ignore the label still in view for this long after it was handled. */
const SAME_CODE_COOLDOWN_MS = 3000
const SCAN_INTERVAL_MS = 150

export type CameraState = 'starting' | 'running' | 'denied' | 'unavailable'

/**
 * The back camera, read for barcodes while the screen that shows it is up —
 * shared by the rider's scanner and the inventory manager's. Put `videoRef`
 * on a <video>; each new barcode in view is passed to `onDetected`.
 *
 * `paused` keeps the camera but stops reading it (a sheet is open over it,
 * or the last scan is still being handled).
 */
export function useBarcodeCamera(paused: boolean, onDetected: (code: string) => void) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const pausedRef = useRef(paused)
  const lastRef = useRef<{ code: string; at: number }>({ code: '', at: 0 })
  const onDetectedRef = useRef(onDetected)

  const [camera, setCamera] = useState<CameraState>('starting')
  const [torchAvailable, setTorchAvailable] = useState(false)
  const [torchOn, setTorchOn] = useState(false)

  onDetectedRef.current = onDetected

  // Reading again after a pause: restart the cooldown so the label that was
  // just handled isn't read again straight away.
  useEffect(() => {
    if (pausedRef.current && !paused) {
      lastRef.current = { ...lastRef.current, at: Date.now() }
    }
    pausedRef.current = paused
  }, [paused])

  useEffect(() => {
    let cancelled = false
    let timer: number | undefined
    let detector: Detector | null = null

    const loop = async () => {
      const video = videoRef.current
      if (cancelled) return

      if (detector && video && !pausedRef.current && video.readyState >= 2) {
        try {
          const found = await detector.detect(video)
          const raw = found.find((b) => b.rawValue?.trim())?.rawValue

          if (raw && !pausedRef.current) {
            const code = normalizeScannedCode(raw)
            const last = lastRef.current
            const repeat = code === last.code && Date.now() - last.at < SAME_CODE_COOLDOWN_MS

            if (!repeat) {
              lastRef.current = { code, at: Date.now() }
              vibrate(80)
              onDetectedRef.current(code)
            }
          }
        } catch {
          // A frame that can't be read — try the next one.
        }
      }

      timer = window.setTimeout(loop, SCAN_INTERVAL_MS)
    }

    const start = async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        setCamera('unavailable')
        return
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: false,
          video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        })

        if (cancelled) {
          stream.getTracks().forEach((track) => track.stop())
          return
        }

        streamRef.current = stream
        const video = videoRef.current!
        video.srcObject = stream
        await video.play().catch(() => {})

        const track = stream.getVideoTracks()[0]
        const capabilities = (track.getCapabilities?.() ?? {}) as { torch?: boolean }
        setTorchAvailable(!!capabilities.torch)

        detector = await createDetector()
        if (cancelled) return

        setCamera('running')
        loop()
      } catch (error) {
        if (cancelled) return
        const name = (error as DOMException)?.name
        setCamera(name === 'NotAllowedError' || name === 'SecurityError' ? 'denied' : 'unavailable')
      }
    }

    start()

    return () => {
      cancelled = true
      window.clearTimeout(timer)
      streamRef.current?.getTracks().forEach((track) => track.stop())
      streamRef.current = null
    }
  }, [])

  const toggleTorch = async () => {
    const track = streamRef.current?.getVideoTracks()[0]
    if (!track) return
    try {
      await track.applyConstraints({ advanced: [{ torch: !torchOn } as MediaTrackConstraintSet] })
      setTorchOn(!torchOn)
    } catch {
      setTorchAvailable(false)
    }
  }

  /** A code handled some other way (typed in): don't read it off the label straight after. */
  const handled = (code: string) => {
    lastRef.current = { code, at: Date.now() }
  }

  return { videoRef, camera, torchAvailable, torchOn, toggleTorch, handled }
}
