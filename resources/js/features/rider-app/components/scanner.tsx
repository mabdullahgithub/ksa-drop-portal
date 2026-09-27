import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Flashlight, Keyboard, Loader2, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useI18n } from '../i18n'
import { createDetector, normalizeScannedCode, type Detector } from '../lib/barcode'
import { vibrate } from '../lib/device'
import type { EntryMethod, ScanMode } from '../types'

type Props = {
  /** True while the update screen is open or a scan is being handled: keep the camera, stop reading. */
  paused: boolean
  onDetected: (code: string, entry: EntryMethod) => void
  onClose: () => void
  mode: ScanMode
  onModeChange: (mode: ScanMode) => void
  /** Show a spinner in the viewfinder (a one-by-one scan is being picked up). */
  busy?: boolean
  /** Viewfinder flashes green or red with the last scan's result. */
  flash?: 'ok' | 'error' | null
  /** Batch pick-up list, shown above the bottom controls. */
  panel?: ReactNode
}

/** Ignore the label still in view for this long after the update screen closes. */
const SAME_CODE_COOLDOWN_MS = 3000
const SCAN_INTERVAL_MS = 150

type CameraState = 'starting' | 'running' | 'denied' | 'unavailable'

export function Scanner({ paused, onDetected, onClose, mode, onModeChange, busy, flash, panel }: Props) {
  const { t } = useI18n()
  const videoRef = useRef<HTMLVideoElement>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const pausedRef = useRef(paused)
  const lastRef = useRef<{ code: string; at: number }>({ code: '', at: 0 })
  const onDetectedRef = useRef(onDetected)

  const [camera, setCamera] = useState<CameraState>('starting')
  const [typing, setTyping] = useState(false)
  const [typed, setTyped] = useState('')
  const [torchAvailable, setTorchAvailable] = useState(false)
  const [torchOn, setTorchOn] = useState(false)

  onDetectedRef.current = onDetected

  // Coming back from the update screen: restart the cooldown so the label
  // that was just handled isn't read again straight away.
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
              onDetectedRef.current(code, 'camera')
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
        setTyping(true)
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
        setTyping(true)
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

  const submitTyped = (event: React.FormEvent) => {
    event.preventDefault()
    const code = normalizeScannedCode(typed)
    if (!code) return
    lastRef.current = { code, at: Date.now() }
    setTyped('')
    onDetected(code, 'manual')
  }

  return (
    <div className='fixed inset-0 z-40 flex flex-col bg-black text-white'>
      <video ref={videoRef} className='absolute inset-0 h-full w-full object-cover' playsInline muted autoPlay />

      {/* Viewfinder */}
      {camera === 'running' && (
        <div
          className={cn(
            'pointer-events-none absolute inset-0 flex justify-center',
            // Batch: sit higher, clear of the list at the bottom.
            mode === 'batch' ? 'items-start pt-[20vh]' : 'items-center'
          )}
        >
          <div
            className={cn(
              'relative h-40 w-[82%] max-w-md rounded-2xl shadow-[0_0_0_9999px_rgba(0,0,0,0.55)] transition-colors',
              flash === 'ok' ? 'border-green-500 bg-green-500/25' : flash === 'error' ? 'border-red-500 bg-red-500/25' : 'border-brand'
            )}
          >
            <span className='absolute -inset-s-0.5 -top-0.5 h-8 w-8 rounded-ss-2xl border-s-4 border-t-4 border-inherit' />
            <span className='absolute -inset-e-0.5 -top-0.5 h-8 w-8 rounded-se-2xl border-e-4 border-t-4 border-inherit' />
            <span className='absolute -bottom-0.5 -inset-s-0.5 h-8 w-8 rounded-es-2xl border-b-4 border-s-4 border-inherit' />
            <span className='absolute -bottom-0.5 -inset-e-0.5 h-8 w-8 rounded-ee-2xl border-b-4 border-e-4 border-inherit' />
            {busy ? (
              <span className='absolute inset-0 flex items-center justify-center'>
                <Loader2 className='h-9 w-9 animate-spin text-white' />
              </span>
            ) : (
              <span className='absolute inset-x-4 top-1/2 h-0.5 -translate-y-1/2 animate-pulse bg-brand/80' />
            )}
          </div>
        </div>
      )}

      {/* Top bar */}
      <div className='relative flex items-center justify-between px-4 pt-[calc(env(safe-area-inset-top)+12px)]'>
        <button
          type='button'
          onClick={onClose}
          className='glass-dark glass-press flex h-11 w-11 items-center justify-center rounded-full'
          aria-label={t('close')}
        >
          <X className='h-6 w-6' />
        </button>

        <div className='glass-dark flex rounded-full p-1'>
          {(['single', 'batch'] as const).map((option) => (
            <button
              key={option}
              type='button'
              onClick={() => onModeChange(option)}
              aria-pressed={mode === option}
              className={cn(
                'glass-press h-9 rounded-full px-3.5 text-[13px] font-semibold',
                mode === option ? 'bg-white text-black' : 'text-white/80'
              )}
            >
              {t(option === 'single' ? 'mode_single' : 'mode_batch')}
            </button>
          ))}
        </div>

        {torchAvailable ? (
          <button
            type='button'
            onClick={toggleTorch}
            aria-label={t('light')}
            className={cn(
              'glass-press flex h-11 w-11 items-center justify-center rounded-full',
              torchOn ? 'glass-tint' : 'glass-dark'
            )}
          >
            <Flashlight className='h-5 w-5' />
          </button>
        ) : (
          <span className='h-11 w-11' />
        )}
      </div>

      <div className='relative mt-auto space-y-3 px-4 pb-[calc(env(safe-area-inset-bottom)+20px)]'>
        {panel}
        {camera === 'running' && !typing && !panel && (
          <p className='text-center text-sm font-medium drop-shadow'>{t('point_camera')}</p>
        )}
        {camera === 'starting' && <p className='text-center text-base'>{t('camera_starting')}</p>}
        {camera === 'denied' && <p className='glass-tint rounded-2xl p-3 text-sm' style={{ '--tint': '#dc2626' } as React.CSSProperties}>{t('camera_denied')}</p>}
        {camera === 'unavailable' && <p className='glass-tint rounded-2xl p-3 text-sm' style={{ '--tint': '#dc2626' } as React.CSSProperties}>{t('camera_unavailable')}</p>}

        {typing ? (
          <form onSubmit={submitTyped} className='glass-dark flex gap-2 rounded-full p-1.5'>
            <input
              autoFocus
              value={typed}
              onChange={(e) => setTyped(e.target.value)}
              placeholder={t('tracking_or_order')}
              autoCapitalize='characters'
              autoCorrect='off'
              spellCheck={false}
              enterKeyHint='search'
              className='h-11 min-w-0 flex-1 rounded-full border-0 bg-white/90 px-4 font-mono text-black outline-none placeholder:text-black/40'
            />
            <button
              type='submit'
              disabled={!typed.trim()}
              className='glass-tint glass-press h-11 rounded-full px-5 text-[15px] font-semibold disabled:opacity-50'
            >
              {t('find')}
            </button>
          </form>
        ) : (
          <button
            type='button'
            onClick={() => setTyping(true)}
            className='glass-dark glass-press mx-auto flex h-11 items-center gap-2 rounded-full px-5 text-sm font-medium'
          >
            <Keyboard className='h-5 w-5' />
            {t('type_number')}
          </button>
        )}
      </div>
    </div>
  )
}
