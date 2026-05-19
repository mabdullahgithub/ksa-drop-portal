import { Head, useForm } from '@inertiajs/react'
import { FormEventHandler, useState, useRef, useEffect } from 'react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ShieldCheck } from 'lucide-react'

export default function TwoFactorChallenge() {
  const { data, setData, post, processing, errors, reset } = useForm({
    code: '',
  })
  const [codeDigits, setCodeDigits] = useState(['', '', '', '', '', ''])
  const [useRecoveryCode, setUseRecoveryCode] = useState(false)
  const inputRefs = useRef<(HTMLInputElement | null)[]>([])
  const recoveryInputRef = useRef<HTMLInputElement>(null)

  const handleCodeChange = (index: number, value: string) => {
    if (value.length > 1) {
      value = value[value.length - 1]
    }

    if (!/^\d*$/.test(value)) return

    const newDigits = [...codeDigits]
    newDigits[index] = value
    setCodeDigits(newDigits)
    setData('code', newDigits.join(''))

    if (value && index < 5) {
      inputRefs.current[index + 1]?.focus()
    }
  }

  const handleKeyDown = (index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace' && !codeDigits[index] && index > 0) {
      inputRefs.current[index - 1]?.focus()
    }
  }

  const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault()
    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6)
    const newDigits = pastedData.split('').concat(Array(6).fill('')).slice(0, 6)
    setCodeDigits(newDigits)
    setData('code', newDigits.join(''))

    const nextEmptyIndex = newDigits.findIndex(d => !d)
    if (nextEmptyIndex !== -1) {
      inputRefs.current[nextEmptyIndex]?.focus()
    } else {
      inputRefs.current[5]?.focus()
    }
  }

  const toggleRecoveryCode = () => {
    setUseRecoveryCode(!useRecoveryCode)
    setData('code', '')
    setCodeDigits(['', '', '', '', '', ''])
  }

  useEffect(() => {
    if (useRecoveryCode) {
      setTimeout(() => {
        recoveryInputRef.current?.focus()
      }, 100)
    } else {
      setTimeout(() => {
        inputRefs.current[0]?.focus()
      }, 100)
    }
  }, [useRecoveryCode])

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('two-factor.login'), {
      onFinish: () => {
        reset('code')
        setCodeDigits(['', '', '', '', '', ''])
      },
    })
  }

  return (
    <div className='flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10'>
      <Head title='Two-Factor Authentication' />
      <div className='flex w-full max-w-sm flex-col gap-6'>
        <Card>
          <CardHeader className='text-center'>
            <div className='mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10'>
              <ShieldCheck className='h-6 w-6 text-primary' />
            </div>
            <CardTitle className='text-xl'>Two-Factor Authentication</CardTitle>
            <CardDescription>
              {useRecoveryCode
                ? 'Enter one of your recovery codes'
                : 'Enter the 6-digit code from your authenticator app'}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit}>
              <div className='grid gap-6'>
                <div className='grid gap-3'>
                  <Label className='text-center'>
                    {useRecoveryCode ? 'Recovery Code' : 'Authentication Code'}
                  </Label>
                  {useRecoveryCode ? (
                    <Input
                      ref={recoveryInputRef}
                      type='text'
                      placeholder='XXXXXXXXXX'
                      maxLength={10}
                      value={data.code}
                      onChange={(e) =>
                        setData('code', e.target.value.toUpperCase())
                      }
                      className={cn(
                        'text-center text-lg font-mono tracking-wider',
                        errors.code && 'border-destructive'
                      )}
                    />
                  ) : (
                    <div className='flex gap-2 justify-center'>
                      {codeDigits.map((digit, index) => (
                        <Input
                          key={index}
                          ref={(el) => (inputRefs.current[index] = el)}
                          type='text'
                          inputMode='numeric'
                          maxLength={1}
                          value={digit}
                          onChange={(e) => handleCodeChange(index, e.target.value)}
                          onKeyDown={(e) => handleKeyDown(index, e)}
                          onPaste={handlePaste}
                          className={cn(
                            'w-12 h-12 text-center text-lg font-semibold',
                            errors.code && 'border-destructive'
                          )}
                        />
                      ))}
                    </div>
                  )}
                  {errors.code && (
                    <p className='text-sm text-destructive text-center'>{errors.code}</p>
                  )}
                </div>
                <Button type='submit' className='w-full' disabled={processing}>
                  Verify
                </Button>
              </div>
              <div className='mt-4 text-center text-sm'>
                <button
                  type='button'
                  onClick={toggleRecoveryCode}
                  className='text-muted-foreground hover:text-foreground transition-colors'
                >
                  {useRecoveryCode
                    ? 'Use authenticator code instead'
                    : 'Use a recovery code instead'}
                </button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
