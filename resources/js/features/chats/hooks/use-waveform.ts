import { useEffect, useState } from 'react'

const BARS = 32

/** Shown while the recording is being read, or if it can't be. */
const PLACEHOLDER = Array<number>(BARS).fill(0.2)

/** One read per recording, however often its bubble is drawn. */
const cache = new Map<string, Promise<number[] | null>>()

async function read(src: string): Promise<number[] | null> {
  const Context = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
  if (!Context) return null

  const response = await fetch(src, { credentials: 'same-origin' })
  if (!response.ok) return null

  const context = new Context()
  try {
    const audio = await context.decodeAudioData(await response.arrayBuffer())
    const samples = audio.getChannelData(0)
    const size = Math.max(1, Math.floor(samples.length / BARS))

    // The loudness (RMS) of each slice, scaled so the loudest bar is full height.
    const levels = Array.from({ length: BARS }, (_, bar) => {
      const end = Math.min(samples.length, (bar + 1) * size)
      let sum = 0
      for (let i = bar * size; i < end; i += 8) sum += samples[i] * samples[i]
      return Math.sqrt(sum / Math.max(1, Math.ceil((end - bar * size) / 8)))
    })
    const loudest = Math.max(...levels)

    return loudest > 0 ? levels.map((level) => level / loudest) : null
  } finally {
    void context.close()
  }
}

/** The shape of a voice recording, as bar heights from 0 to 1. */
export function useWaveform(src: string): number[] {
  const [bars, setBars] = useState<number[]>(PLACEHOLDER)

  useEffect(() => {
    if (!src) return
    let cancelled = false

    let pending = cache.get(src)
    if (!pending) {
      pending = read(src).catch(() => null)
      cache.set(src, pending)
    }

    pending.then((result) => {
      if (!cancelled) setBars(result ?? PLACEHOLDER)
    })

    return () => {
      cancelled = true
    }
  }, [src])

  return bars
}
