const BARS = 32

/**
 * The shape of a recording as bar heights from 0 to 100, worked out in the
 * browser that recorded it and sent with it, so showing a voice message never
 * means downloading it. Null where the browser can't read its own recording.
 */
export async function waveformOf(recording: Blob): Promise<number[] | null> {
  // Offline: it decodes without opening the sound device.
  if (typeof OfflineAudioContext === 'undefined') return null

  try {
    const context = new OfflineAudioContext(1, 1, 44100)
    const audio = await context.decodeAudioData(await recording.arrayBuffer())
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

    return loudest > 0 ? levels.map((level) => Math.round((level / loudest) * 100)) : null
  } catch {
    return null
  }
}
