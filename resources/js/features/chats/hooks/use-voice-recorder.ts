import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'
import { MAX_VOICE_SECONDS } from '../constants'
import type { VoiceClip } from '../types'

/**
 * Best first: AAC in MP4 plays in every browser, Safari included. The others
 * are for browsers that can't record it (Firefox).
 */
const TYPES = ['audio/mp4;codecs=mp4a.40.2', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']

type RecorderState = 'idle' | 'starting' | 'recording' | 'review'

/**
 * Recording a voice message with the browser's microphone: record, then
 * either send straight away or stop to listen first. The microphone is
 * released as soon as recording stops, is discarded, or the box goes away.
 */
export function useVoiceRecorder() {
  const [state, setState] = useState<RecorderState>('idle')
  const [seconds, setSeconds] = useState(0)
  const [clip, setClip] = useState<VoiceClip | null>(null)

  const recorder = useRef<MediaRecorder | null>(null)
  const stream = useRef<MediaStream | null>(null)
  const timer = useRef<ReturnType<typeof setInterval> | null>(null)
  const startedAt = useRef(0)
  /** Bumped to abandon whatever is in progress, a pending permission prompt included. */
  const session = useRef(0)
  const waiting = useRef<((clip: VoiceClip | null) => void) | null>(null)

  const release = useCallback(() => {
    if (timer.current) clearInterval(timer.current)
    timer.current = null
    stream.current?.getTracks().forEach((track) => track.stop())
    stream.current = null
    recorder.current = null
  }, [])

  /** Stop recording and hand back the clip, which also becomes the one to review. */
  const stop = useCallback((): Promise<VoiceClip | null> => {
    const active = recorder.current
    if (!active || active.state === 'inactive') return Promise.resolve(null)
    return new Promise((resolve) => {
      waiting.current = resolve
      active.stop()
    })
  }, [])

  const reset = useCallback(() => {
    setClip(null)
    setSeconds(0)
    setState('idle')
  }, [])

  /** Throw away the recording, whatever stage it is at. */
  const discard = useCallback(() => {
    session.current++
    waiting.current?.(null)
    waiting.current = null
    if (recorder.current && recorder.current.state !== 'inactive') recorder.current.stop()
    release()
    setClip((current) => {
      if (current) URL.revokeObjectURL(current.url)
      return null
    })
    setSeconds(0)
    setState('idle')
  }, [release])

  const start = useCallback(async () => {
    if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      toast.error(
        window.isSecureContext
          ? 'This browser cannot record voice messages.'
          : 'Voice messages need a secure (https) connection.'
      )
      return
    }

    const mine = ++session.current
    setState('starting')

    let media: MediaStream
    try {
      media = await navigator.mediaDevices.getUserMedia({ audio: true })
    } catch (error) {
      if (mine !== session.current) return
      setState('idle')
      const name = (error as DOMException)?.name
      toast.error(
        name === 'NotAllowedError'
          ? 'Microphone access is blocked. Allow it in your browser to record.'
          : name === 'NotFoundError'
            ? 'No microphone was found.'
            : 'Could not use the microphone.'
      )
      return
    }

    // Cancelled while the browser was still asking for permission.
    if (mine !== session.current) {
      media.getTracks().forEach((track) => track.stop())
      return
    }

    const mimeType = TYPES.find((type) => MediaRecorder.isTypeSupported(type))
    let next: MediaRecorder
    try {
      next = new MediaRecorder(media, { ...(mimeType ? { mimeType } : {}), audioBitsPerSecond: 32000 })
    } catch {
      media.getTracks().forEach((track) => track.stop())
      setState('idle')
      toast.error('This browser cannot record voice messages.')
      return
    }

    const chunks: Blob[] = []
    next.ondataavailable = (event) => event.data.size > 0 && chunks.push(event.data)
    next.onstop = () => {
      const length = Date.now() - startedAt.current
      if (mine !== session.current) return
      release()

      const type = (next.mimeType || mimeType || 'audio/webm').split(';')[0]
      const blob = new Blob(chunks, { type })
      const done = waiting.current
      waiting.current = null

      if (length < 800 || blob.size === 0) {
        setSeconds(0)
        setState('idle')
        toast.info('That recording was too short.')
        done?.(null)
        return
      }

      const extension = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm'
      const result: VoiceClip = {
        file: new File([blob], `voice-message.${extension}`, { type }),
        seconds: Math.min(MAX_VOICE_SECONDS, Math.max(1, Math.round(length / 1000))),
        url: URL.createObjectURL(blob),
      }
      setClip(result)
      setState('review')
      done?.(result)
    }

    stream.current = media
    recorder.current = next
    startedAt.current = Date.now()
    setSeconds(0)
    setState('recording')
    next.start()

    timer.current = setInterval(() => {
      const elapsed = Math.floor((Date.now() - startedAt.current) / 1000)
      setSeconds(Math.min(elapsed, MAX_VOICE_SECONDS))
      // At the limit it stops for a listen; nothing is sent on its own.
      if (elapsed >= MAX_VOICE_SECONDS) void stop()
    }, 250)
  }, [release, stop])

  // Leaving the page or the chat never leaves the microphone on.
  useEffect(
    () => () => {
      session.current++
      if (recorder.current && recorder.current.state !== 'inactive') recorder.current.stop()
      release()
    },
    [release]
  )

  return { state, seconds, clip, start, stop, discard, reset }
}
