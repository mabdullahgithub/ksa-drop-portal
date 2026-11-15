import { useEffect, useRef, useState } from 'react'
import { motion } from 'framer-motion'

/** Fades and lifts its children into view once, when scrolled to. */
export function Reveal({
  children,
  delay = 0,
  className,
}: {
  children: React.ReactNode
  delay?: number
  className?: string
}) {
  return (
    <motion.div
      className={className}
      initial={{ opacity: 0, y: 16 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.5, delay, ease: 'easeOut' }}
    >
      {children}
    </motion.div>
  )
}

export type IllustrationName = 'hero' | 'seller' | 'agency' | 'influencer' | 'payout' | 'cta'

/**
 * Animated Icons8 "3D Techny" illustration (transparent video, 1200px).
 * Order of sources matters: HEVC first for Safari, WebM for the rest.
 * The video only mounts once it is near the viewport, and users who prefer
 * reduced motion get the still poster instead.
 */
export function Illustration({
  name,
  className = '',
  eager = false,
}: {
  name: IllustrationName
  className?: string
  eager?: boolean
}) {
  const ref = useRef<HTMLDivElement>(null)
  const [near, setNear] = useState(eager)
  const [still, setStill] = useState(false)

  useEffect(() => {
    setStill(window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false)
    if (eager || !ref.current || typeof IntersectionObserver === 'undefined') {
      setNear(true)
      return
    }
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setNear(true)
          io.disconnect()
        }
      },
      { rootMargin: '200px' },
    )
    io.observe(ref.current)
    return () => io.disconnect()
  }, [eager])

  const base = `/images/partners/illustrations/${name}`

  return (
    <div ref={ref} className={`aspect-square ${className}`}>
      {near && !still ? (
        <video
          autoPlay
          loop
          muted
          playsInline
          poster={`${base}.webp`}
          aria-hidden='true'
          className='h-full w-full object-contain'
        >
          <source src={`${base}.mp4`} type='video/mp4; codecs=hvc1' />
          <source src={`${base}.webm`} type='video/webm' />
        </video>
      ) : (
        <img src={`${base}.webp`} alt='' aria-hidden='true' className='h-full w-full object-contain' />
      )}
    </div>
  )
}
