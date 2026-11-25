import { cn } from '@/lib/utils'

/**
 * A success tick that draws itself: the ring first, then the check, with a
 * small bounce and one soft ripple. Plain SVG and CSS. `pathLength={1}` lets
 * one dash rule draw any shape. With reduced motion it is simply shown.
 */
const CSS = `
.success-check { overflow: visible; }
.success-check * { transform-box: fill-box; transform-origin: center; }
.success-check .sc-ring, .success-check .sc-tick { stroke-dasharray: 1; stroke-dashoffset: 0; }
@media (prefers-reduced-motion: no-preference) {
  .success-check .sc-ring { animation: sc-draw .6s cubic-bezier(.65,0,.35,1) .15s both; }
  .success-check .sc-tick { animation: sc-draw .35s cubic-bezier(.65,0,.35,1) .7s both; }
  .success-check .sc-body { animation: sc-pop .45s cubic-bezier(.34,1.56,.64,1) 1s both; }
  .success-check .sc-fill { animation: sc-fill .4s ease-out .9s both; }
  .success-check .sc-ripple { animation: sc-ripple .8s ease-out 1.05s forwards; }
}
.success-check .sc-ripple { opacity: 0; }
@keyframes sc-draw { from { stroke-dashoffset: 1; opacity: 0; } 1% { opacity: 1; } to { stroke-dashoffset: 0; opacity: 1; } }
@keyframes sc-pop { 0%, 100% { transform: scale(1); } 45% { transform: scale(1.1); } }
@keyframes sc-fill { from { opacity: 0; transform: scale(.4); } to { opacity: .14; transform: scale(1); } }
@keyframes sc-ripple { from { opacity: .5; transform: scale(1); } to { opacity: 0; transform: scale(1.35); } }
`

export function SuccessCheck({ className }: { className?: string }) {
  return (
    <svg
      viewBox='0 0 100 100'
      fill='none'
      stroke='currentColor'
      strokeLinecap='round'
      strokeLinejoin='round'
      className={cn('success-check', className)}
      aria-hidden
    >
      <style>{CSS}</style>
      <circle className='sc-ripple' cx='50' cy='50' r='44' strokeWidth='3' />
      <g className='sc-body'>
        <circle className='sc-fill' cx='50' cy='50' r='44' fill='currentColor' stroke='none' opacity='.14' />
        <circle
          className='sc-ring'
          cx='50'
          cy='50'
          r='44'
          strokeWidth='6'
          pathLength={1}
          // The ring starts drawing at the top.
          transform='rotate(-90 50 50)'
          style={{ transformBox: 'view-box', transformOrigin: '0 0' }}
        />
        <path className='sc-tick' d='M30 52 L44 66 L71 37' strokeWidth='7' pathLength={1} />
      </g>
    </svg>
  )
}
