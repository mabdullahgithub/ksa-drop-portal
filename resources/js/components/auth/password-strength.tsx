import { Check, Circle } from 'lucide-react'
import { cn } from '@/lib/utils'
import { checkSignupPassword } from '@/lib/signup-password'

const BAR = ['', 'bg-red-500', 'bg-yellow-500', 'bg-lime-500', 'bg-green-500']

export function PasswordStrength({ result }: { result: ReturnType<typeof checkSignupPassword> }) {
  return (
    <div className='space-y-2.5'>
      <div className='flex items-center gap-3'>
        <div className='grid flex-1 grid-cols-4 gap-1.5' aria-hidden>
          {[1, 2, 3, 4].map((segment) => (
            <span
              key={segment}
              className={cn(
                'h-1.5 rounded-full transition-colors',
                segment <= result.score ? BAR[result.score] : 'bg-muted',
              )}
            />
          ))}
        </div>
        <span className='w-20 text-right text-xs font-medium' aria-live='polite'>
          {result.label}
        </span>
      </div>
      <ul className='space-y-1'>
        {result.rules.map((rule) => (
          <li
            key={rule.label}
            className={cn(
              'flex items-center gap-2 text-xs',
              rule.met ? 'text-green-600 dark:text-green-500' : 'text-muted-foreground',
            )}
          >
            {rule.met ? <Check className='h-3.5 w-3.5' /> : <Circle className='h-3.5 w-3.5' />}
            {rule.label}
            <span className='sr-only'>{rule.met ? '(met)' : '(not met yet)'}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
