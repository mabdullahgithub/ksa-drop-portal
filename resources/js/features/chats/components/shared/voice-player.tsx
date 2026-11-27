import { useEffect, useRef, useState } from 'react'
import { Download, Pause, Play } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useWaveform } from '../../hooks/use-waveform'

/** Only one voice message plays at a time. */
let playing: HTMLAudioElement | null = null

const SPEEDS = [1, 1.5, 2]

export function clock(seconds: number) {
  const whole = Math.max(0, Math.floor(seconds))
  return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`
}

/**
 * A compact player for a voice message: play, the recording's own waveform
 * (click or arrow keys to move in it), the time and the playback speed. The
 * length comes with the message, since browser recordings often carry none.
 */
export function VoicePlayer({
  src,
  duration,
  onDark,
  className,
}: {
  src: string
  duration: number
  /** On the brand-coloured bubble of a sent message. */
  onDark?: boolean
  className?: string
}) {
  const audio = useRef<HTMLAudioElement>(null)
  const [isPlaying, setIsPlaying] = useState(false)
  const [time, setTime] = useState(0)
  const [speed, setSpeed] = useState(1)
  const [failed, setFailed] = useState(false)
  const bars = useWaveform(src)
  const total = duration || 1
  const progress = Math.min(1, time / total)
  const started = isPlaying || time > 0

  useEffect(() => {
    const element = audio.current
    return () => element?.pause()
  }, [])

  // timeupdate fires only a few times a second; this keeps the waveform smooth.
  useEffect(() => {
    if (!isPlaying) return
    let frame = requestAnimationFrame(function tick() {
      if (audio.current) setTime(audio.current.currentTime)
      frame = requestAnimationFrame(tick)
    })
    return () => cancelAnimationFrame(frame)
  }, [isPlaying])

  const toggle = () => {
    const element = audio.current
    if (!element) return
    if (!element.paused) {
      element.pause()
      return
    }
    playing?.pause()
    playing = element
    element.playbackRate = speed
    element.play().catch(() => setFailed(true))
  }

  const nextSpeed = () => {
    const next = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length]
    setSpeed(next)
    if (audio.current) audio.current.playbackRate = next
  }

  if (failed) {
    return (
      <a href={src} download className={cn('flex items-center gap-2 py-1 text-xs underline-offset-2 hover:underline', className)}>
        <Download className='size-4 shrink-0' />
        This browser can&apos;t play it. Download the voice message ({clock(duration)})
      </a>
    )
  }

  return (
    <div className={cn('flex w-60 max-w-full items-center gap-2.5 py-0.5', className)}>
      <button
        type='button'
        onClick={toggle}
        disabled={!src}
        aria-label={isPlaying ? 'Pause voice message' : 'Play voice message'}
        className={cn(
          'flex size-8 shrink-0 items-center justify-center rounded-full shadow-sm transition-transform focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-95',
          onDark ? 'bg-white text-[#f26722]' : 'bg-[#f26722] text-white'
        )}
      >
        {isPlaying ? <Pause className='size-3.5 fill-current' strokeWidth={0} /> : <Play className='ms-0.5 size-3.5 fill-current' strokeWidth={0} />}
      </button>

      <div className='min-w-0 flex-1'>
        <div className='relative flex h-6 items-center gap-[2px]'>
          {bars.map((height, index) => (
            <span
              key={index}
              aria-hidden
              className={cn(
                'min-w-0 flex-1 rounded-full',
                (index + 0.5) / bars.length <= progress
                  ? onDark ? 'bg-white' : 'bg-[#f26722]'
                  : onDark ? 'bg-white/40' : 'bg-foreground/25'
              )}
              style={{ height: `${Math.max(12, height * 100)}%` }}
            />
          ))}
          {/* Invisible, over the bars: dragging, clicking and the keyboard come for free. */}
          <input
            type='range'
            min={0}
            max={total}
            step={0.1}
            value={Math.min(time, total)}
            onChange={(event) => {
              const next = Number(event.target.value)
              setTime(next)
              if (audio.current) audio.current.currentTime = next
            }}
            aria-label='Position in the voice message'
            aria-valuetext={`${clock(time)} of ${clock(duration)}`}
            className='peer absolute inset-0 size-full cursor-pointer opacity-0'
          />
          <span className='pointer-events-none absolute -inset-x-1 inset-y-0 rounded peer-focus-visible:ring-2 peer-focus-visible:ring-ring' />
        </div>
        <div className={cn('mt-0.5 text-[10px] leading-none tabular-nums', onDark ? 'text-white/85' : 'text-muted-foreground')}>
          {started ? `${clock(time)} / ${clock(duration)}` : clock(duration)}
        </div>
      </div>

      <button
        type='button'
        onClick={nextSpeed}
        aria-label={`Playback speed ${speed}×. Change speed`}
        className={cn(
          'w-8 shrink-0 rounded-md py-0.5 text-center text-[10px] font-semibold tabular-nums transition-colors',
          onDark ? 'bg-white/20 hover:bg-white/30' : 'bg-foreground/10 hover:bg-foreground/15'
        )}
      >
        {speed}×
      </button>

      <audio
        ref={audio}
        src={src || undefined}
        preload='none'
        onPlay={() => setIsPlaying(true)}
        onPause={() => setIsPlaying(false)}
        onEnded={() => {
          setIsPlaying(false)
          setTime(0)
        }}
        onError={() => src && setFailed(true)}
      />
    </div>
  )
}
