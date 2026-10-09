import confetti from 'canvas-confetti'

/** A short burst of confetti from both bottom corners. */
export function celebrate() {
  const shared = {
    particleCount: 80,
    spread: 70,
    startVelocity: 55,
    // Above dialogs and their backdrop.
    zIndex: 100,
    disableForReducedMotion: true,
  }

  confetti({ ...shared, angle: 60, origin: { x: 0, y: 0.9 } })
  confetti({ ...shared, angle: 120, origin: { x: 1, y: 0.9 } })
}
