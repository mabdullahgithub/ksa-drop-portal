import { Check, Circle } from 'lucide-react'
import { cn } from '@/lib/utils'
import { checkSignupPassword } from '@/lib/signup-password'

const COLOR = ['', 'bg-red-500', 'bg-yellow-500', 'bg-lime-500', 'bg-green-500']
const TEXT = [
  'text-muted-foreground',
  'text-red-500',
  'text-yellow-500',
  'text-lime-500',
  'text-green-500',
]
/**
 * Once the password passes every rule the tower slowly "breathes": a soft
 * glow, and a gentle wave that drifts up through the tiles. Long and slow on
 * purpose; with reduced motion it just stays lit.
 */
const CSS = `
@media (prefers-reduced-motion: no-preference) {
  .pw-tower.is-strong { animation: pw-breathe 3.2s ease-in-out infinite; }
  .pw-tower.is-strong .pw-tile { animation: pw-wave 3.2s ease-in-out infinite; }
}
@keyframes pw-breathe {
  0%, 100% { filter: drop-shadow(0 0 0 transparent); }
  50% { filter: drop-shadow(0 0 6px rgb(34 197 94 / .55)); }
}
@keyframes pw-wave {
  0%, 100% { opacity: 1; }
  50% { opacity: .55; }
}
`

/**
 * The password rules, each ticked as it is met, beside a small tower whose
 * bars rise and change colour with the strength.
 */
export function PasswordStrength({ result }: { result: ReturnType<typeof checkSignupPassword> }) {
  return (
    <div className='flex items-stretch justify-between gap-4'>
      <ul className='space-y-0.5'>
        {result.rules.map((rule) => (
          <li
            key={rule.label}
            className={cn(
              'flex items-center gap-1.5 text-[11px] leading-4 transition-colors',
              rule.met ? 'text-green-600 dark:text-green-500' : 'text-muted-foreground',
            )}
          >
            {rule.met ? (
              <Check className='h-3 w-3 shrink-0' aria-hidden />
            ) : (
              <Circle className='h-3 w-3 shrink-0' aria-hidden />
            )}
            {rule.label}
            {/* Read out, never copied with the text. */}
            <span className='sr-only select-none'>{rule.met ? '(met)' : '(not met yet)'}</span>
          </li>
        ))}
      </ul>

      {/* The tower is as tall as the rules beside it; the label sits on its left. */}
      <div className='flex shrink-0 items-center gap-2.5'>
        <style>{CSS}</style>
        <span
          className={cn('min-w-16 text-right text-[10px] font-medium', TEXT[result.score])}
          aria-live='polite'
        >
          {result.label || 'Strength'}
        </span>
        <div
          className={cn(
            'pw-tower flex h-full w-7 flex-col-reverse gap-1',
            result.valid && 'is-strong',
          )}
          aria-hidden
        >
          {[0, 1, 2, 3].map((index) => (
            <span
              key={index}
              className={cn(
                'pw-tile flex-1 rounded-sm transition-colors duration-500 ease-out',
                index < result.score ? COLOR[result.score] : 'bg-muted',
              )}
              style={{
                // Filling climbs the tower one tile after another; the wave
                // rises the same way.
                transitionDelay: `${index * 90}ms`,
                animationDelay: `${index * 0.25}s`,
              }}
            />
          ))}
        </div>
      </div>
    </div>
  )
}
