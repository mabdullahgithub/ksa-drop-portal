import { X, Copy, Eye, EyeOff, RefreshCw, Sparkles } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { checkPasswordStrength } from '@/lib/password-utils'

interface PasswordGeneratorModalProps {
  isOpen: boolean
  onClose: () => void
  generatedPassword: string
  showPassword: boolean
  onToggleShowPassword: () => void
  onCopyPassword: () => void
  onRegeneratePassword: () => void
  onUsePassword: () => void
}

export function PasswordGeneratorModal({
  isOpen,
  onClose,
  generatedPassword,
  showPassword,
  onToggleShowPassword,
  onCopyPassword,
  onRegeneratePassword,
  onUsePassword,
}: PasswordGeneratorModalProps) {
  if (!isOpen) return null

  const passwordStrength = generatedPassword ? checkPasswordStrength(generatedPassword) : null

  return (
    <>
      {/* Backdrop */}
      <div
        className='fixed inset-0 z-50 bg-black/50 animate-in fade-in-0'
        onClick={onClose}
      />

      {/* Modal */}
      <div className='fixed left-[50%] top-[50%] z-50 w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] animate-in fade-in-0 zoom-in-95 sm:max-w-lg'>
        <div className='relative rounded-lg border bg-background shadow-lg'>
          {/* Close Button */}
          <button
            onClick={onClose}
            className='absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2'
          >
            <X className='h-4 w-4' />
            <span className='sr-only'>Close</span>
          </button>

          {/* Content */}
          <div className='p-6 space-y-4'>
            {/* Header */}
            <div className='flex items-start gap-3 pr-8'>
              <div className='flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10'>
                <Sparkles size={20} className='text-primary' />
              </div>
              <div className='space-y-1'>
                <h2 className='text-lg font-semibold leading-none'>Generate Strong Password</h2>
                <p className='text-sm text-muted-foreground'>
                  A secure password has been generated for you. You can use it or generate a new one.
                </p>
              </div>
            </div>

            {/* Password Input */}
            <div className='space-y-3'>
              <Label className='text-sm font-medium'>Generated Password</Label>
              <div className='relative'>
                <Input
                  type={showPassword ? 'text' : 'password'}
                  value={generatedPassword}
                  readOnly
                  className='font-mono pr-24 h-11 text-sm bg-muted/50 border-muted-foreground/20 focus-visible:ring-primary'
                />
                <div className='absolute right-1 top-1 flex items-center gap-1'>
                  <Button
                    type='button'
                    size='icon'
                    variant='ghost'
                    className='h-9 w-9 hover:bg-background'
                    onClick={onToggleShowPassword}
                  >
                    {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                  </Button>
                  <Button
                    type='button'
                    size='icon'
                    variant='ghost'
                    className='h-9 w-9 hover:bg-background'
                    onClick={onCopyPassword}
                  >
                    <Copy size={16} />
                  </Button>
                </div>
              </div>

              {/* Password Strength */}
              {passwordStrength && (
                <div className='rounded-lg bg-muted/50 p-3 space-y-2'>
                  <div className='flex items-center justify-between'>
                    <span className='text-xs font-medium text-muted-foreground'>Password Strength</span>
                    <span className='text-xs font-semibold text-green-600 dark:text-green-500'>
                      {passwordStrength.label}
                    </span>
                  </div>
                  <div className='h-2 bg-background rounded-full overflow-hidden'>
                    <div
                      className='h-full bg-gradient-to-r from-green-500 to-green-600 transition-all duration-300'
                      style={{ width: `${passwordStrength.percentage}%` }}
                    />
                  </div>
                </div>
              )}
            </div>

            {/* Action Buttons */}
            <div className='flex gap-3 pt-2'>
              <Button
                type='button'
                variant='outline'
                onClick={onRegeneratePassword}
                className='flex-1 h-10'
              >
                <RefreshCw size={16} className='mr-2' />
                Generate Again
              </Button>
              <Button
                type='button'
                onClick={onUsePassword}
                className='flex-1 h-10'
              >
                <Sparkles size={16} className='mr-2' />
                Use This Password
              </Button>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
