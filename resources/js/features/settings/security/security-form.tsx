import { useForm, usePage } from '@inertiajs/react'
import { KeyRound, ShieldCheck, ShieldOff, Copy, RefreshCw, Download, X, Sparkles, Eye, EyeOff, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Switch } from '@/components/ui/switch'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { useState, useRef, useEffect } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { checkPasswordStrength, generateStrongPassword, isDefaultPassword, type PasswordStrength } from '@/lib/password-utils'
import { PasswordGeneratorModal } from '@/components/password-generator-modal'

export function SecurityForm() {
  const { auth, twoFactorQrCodeUrl, twoFactorSecret, recoveryCodes } = usePage().props as any
  const user = auth.user
  const [showQrCode, setShowQrCode] = useState(false)
  const [showRecoveryCodes, setShowRecoveryCodes] = useState(false)
  const [showDisableDialog, setShowDisableDialog] = useState(false)
  const [codeDigits, setCodeDigits] = useState(['', '', '', '', '', ''])
  const inputRefs = useRef<(HTMLInputElement | null)[]>([])
  const [showPasswordGenerator, setShowPasswordGenerator] = useState(false)
  const [generatedPassword, setGeneratedPassword] = useState('')
  const [passwordStrength, setPasswordStrength] = useState<PasswordStrength | null>(null)
  const [showNewPassword, setShowNewPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [showGeneratedPassword, setShowGeneratedPassword] = useState(false)

  const handleCodeChange = (index: number, value: string) => {
    if (value.length > 1) {
      value = value[value.length - 1]
    }

    if (!/^\d*$/.test(value)) return

    const newDigits = [...codeDigits]
    newDigits[index] = value
    setCodeDigits(newDigits)
    confirmForm.setData('code', newDigits.join(''))

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
    confirmForm.setData('code', newDigits.join(''))

    const nextEmptyIndex = newDigits.findIndex(d => !d)
    if (nextEmptyIndex !== -1) {
      inputRefs.current[nextEmptyIndex]?.focus()
    } else {
      inputRefs.current[5]?.focus()
    }
  }

  useEffect(() => {
    if (showQrCode) {
      setTimeout(() => {
        inputRefs.current[0]?.focus()
      }, 100)
    }
  }, [showQrCode])

  function handleCancelSetup() {
    setShowQrCode(false)
    setCodeDigits(['', '', '', '', '', ''])
    confirmForm.reset()
  }

  const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  })

  useEffect(() => {
    if (passwordForm.data.password) {
      const strength = checkPasswordStrength(passwordForm.data.password)
      setPasswordStrength(strength)
    } else {
      setPasswordStrength(null)
    }
  }, [passwordForm.data.password])

  const confirmForm = useForm({
    code: '',
  })

  const disableForm = useForm({
    password: '',
  })

  function handlePasswordSubmit(e: React.FormEvent) {
    e.preventDefault()
    passwordForm.put(route('settings.password.update'), {
      onSuccess: () => {
        passwordForm.reset()
        toast.success('Password updated successfully.')
      },
    })
  }

  function handleEnableTwoFactor() {
    confirmForm.post(route('settings.two-factor.enable'), {
      onSuccess: () => {
        setShowQrCode(true)
        toast.success('Scan the QR code with your authenticator app.')
      },
    })
  }

  function handleConfirmTwoFactor(e: React.FormEvent) {
    e.preventDefault()
    confirmForm.post(route('settings.two-factor.confirm'), {
      onSuccess: () => {
        setShowQrCode(false)
        setShowRecoveryCodes(true)
        confirmForm.reset()
        setCodeDigits(['', '', '', '', '', ''])
        toast.success('Two-factor authentication enabled successfully.')
      },
      onError: () => {
        toast.error('Invalid code. Please try again.')
      },
    })
  }

  function handleDisableTwoFactor(e: React.FormEvent) {
    e.preventDefault()
    disableForm.delete(route('settings.two-factor.disable'), {
      onSuccess: () => {
        setShowDisableDialog(false)
        disableForm.reset()
        toast.success('Two-factor authentication disabled.')
      },
      onError: () => {
        toast.error('Invalid password. Please try again.')
      },
    })
  }

  function handleRegenerateRecoveryCodes() {
    confirmForm.post(route('settings.two-factor.recovery-codes.regenerate'), {
      onSuccess: () => {
        setShowRecoveryCodes(true)
        toast.success('New recovery codes generated.')
      },
    })
  }

  function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text)
    toast.success('Copied to clipboard!')
  }

  function downloadRecoveryCodes() {
    const codesText = recoveryCodes?.join('\n') || ''
    const blob = new Blob([codesText], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `recovery-codes-${new Date().toISOString().split('T')[0]}.txt`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    toast.success('Recovery codes downloaded!')
  }

  function copySecret() {
    if (twoFactorSecret) {
      navigator.clipboard.writeText(twoFactorSecret)
      toast.success('Setup key copied to clipboard!')
    }
  }

  function handleGeneratePassword() {
    const newPassword = generateStrongPassword(16)
    setGeneratedPassword(newPassword)
    setShowPasswordGenerator(true)
  }

  function handleUseGeneratedPassword() {
    passwordForm.setData('password', generatedPassword)
    passwordForm.setData('password_confirmation', generatedPassword)
    setShowPasswordGenerator(false)
    toast.success('Generated password applied!')
  }

  function handleRegeneratePassword() {
    const newPassword = generateStrongPassword(16)
    setGeneratedPassword(newPassword)
    toast.success('New password generated!')
  }

  function copyGeneratedPassword() {
    navigator.clipboard.writeText(generatedPassword)
    toast.success('Password copied to clipboard!')
  }

  return (
    <div className='space-y-8'>
      <div>
        <div className='flex items-center gap-2 mb-4'>
          <KeyRound size={18} className='text-muted-foreground' />
          <h4 className='text-sm font-medium'>Change Password</h4>
        </div>
        <form onSubmit={handlePasswordSubmit} className='space-y-4'>
          <div className='space-y-2'>
            <Label htmlFor='current_password'>Current Password</Label>
            <Input
              id='current_password'
              type='password'
              value={passwordForm.data.current_password}
              onChange={(e) =>
                passwordForm.setData('current_password', e.target.value)
              }
            />
            {passwordForm.errors.current_password && (
              <p className='text-destructive text-xs'>
                {passwordForm.errors.current_password}
              </p>
            )}
          </div>

          <div className='space-y-2'>
            <div className='flex items-center justify-between'>
              <Label htmlFor='password'>New Password</Label>
              <Button
                type='button'
                variant='ghost'
                size='sm'
                onClick={handleGeneratePassword}
                className='h-auto py-1 px-2 text-xs'
              >
                <Sparkles size={14} className='mr-1' />
                Generate Strong Password
              </Button>
            </div>
            <div className='relative'>
              <Input
                id='password'
                type={showNewPassword ? 'text' : 'password'}
                value={passwordForm.data.password}
                onChange={(e) =>
                  passwordForm.setData('password', e.target.value)
                }
              />
              <Button
                type='button'
                variant='ghost'
                size='icon'
                className='absolute right-0 top-0 h-full px-3'
                onClick={() => setShowNewPassword(!showNewPassword)}
              >
                {showNewPassword ? <EyeOff size={16} /> : <Eye size={16} />}
              </Button>
            </div>
            {passwordStrength && (
              <div className='space-y-2'>
                <div className='flex items-center justify-between text-xs'>
                  <span className='text-muted-foreground'>Password strength:</span>
                  <span className={`font-medium ${
                    passwordStrength.score <= 1 ? 'text-red-500' :
                    passwordStrength.score === 2 ? 'text-yellow-500' :
                    passwordStrength.score === 3 ? 'text-lime-500' :
                    'text-green-500'
                  }`}>
                    {passwordStrength.label}
                  </span>
                </div>
                <div className='h-2 bg-muted rounded-full overflow-hidden'>
                  <div
                    className={`h-full transition-all duration-300 ${passwordStrength.color}`}
                    style={{ width: `${passwordStrength.percentage}%` }}
                  />
                </div>
                {passwordStrength.suggestions.length > 0 && (
                  <ul className='text-xs text-muted-foreground space-y-1'>
                    {passwordStrength.suggestions.map((suggestion, idx) => (
                      <li key={idx}>• {suggestion}</li>
                    ))}
                  </ul>
                )}
                {isDefaultPassword(passwordForm.data.password) && (
                  <Alert variant='destructive' className='py-2'>
                    <AlertTriangle size={14} />
                    <AlertDescription className='text-xs'>
                      This password appears to be a common or default password. Please choose a stronger one.
                    </AlertDescription>
                  </Alert>
                )}
              </div>
            )}
            {passwordForm.errors.password && (
              <p className='text-destructive text-xs'>
                {passwordForm.errors.password}
              </p>
            )}
          </div>

          <div className='space-y-2'>
            <Label htmlFor='password_confirmation'>Confirm New Password</Label>
            <div className='relative'>
              <Input
                id='password_confirmation'
                type={showConfirmPassword ? 'text' : 'password'}
                value={passwordForm.data.password_confirmation}
                onChange={(e) =>
                  passwordForm.setData('password_confirmation', e.target.value)
                }
              />
              <Button
                type='button'
                variant='ghost'
                size='icon'
                className='absolute right-0 top-0 h-full px-3'
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
              >
                {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
              </Button>
            </div>
          </div>

          <Button type='submit' disabled={passwordForm.processing}>
            Update Password
          </Button>
        </form>
      </div>

      <Separator />

      <div>
        <div className='flex items-center gap-2 mb-4'>
          {user.two_factor_enabled ? (
            <ShieldCheck size={18} className='text-green-600' />
          ) : (
            <ShieldOff size={18} className='text-muted-foreground' />
          )}
          <h4 className='text-sm font-medium'>Two-Factor Authentication</h4>
        </div>
        <p className='text-muted-foreground text-sm mb-4'>
          Add an extra layer of security to your account by requiring a
          verification code from Google Authenticator in addition to your
          password.
        </p>

        {!user.two_factor_enabled && !showQrCode && (
          <Button onClick={handleEnableTwoFactor} disabled={confirmForm.processing}>
            Enable Two-Factor Authentication
          </Button>
        )}

        {showQrCode && twoFactorQrCodeUrl && (
          <div className='space-y-6 rounded-lg border p-6 relative'>
            <Button
              variant='ghost'
              size='icon'
              className='absolute top-2 right-2'
              onClick={handleCancelSetup}
            >
              <X size={18} />
            </Button>
            <div className='flex flex-col items-center'>
              <p className='text-sm font-medium mb-4'>
                1. Scan this QR code with Google Authenticator
              </p>
              <div className='bg-white p-4 inline-block rounded'>
                <QRCodeSVG
                  value={twoFactorQrCodeUrl}
                  size={192}
                  level='M'
                />
              </div>
              {twoFactorSecret && (
                <div className='mt-4 flex items-center gap-2 text-xs text-muted-foreground'>
                  <span>Setup key:</span>
                  <code className='font-mono'>{twoFactorSecret}</code>
                  <Button
                    size='icon'
                    variant='ghost'
                    className='h-6 w-6'
                    onClick={copySecret}
                  >
                    <Copy size={12} />
                  </Button>
                </div>
              )}
            </div>

            <form onSubmit={handleConfirmTwoFactor} className='space-y-4'>
              <div className='space-y-3'>
                <Label className='text-sm font-medium block text-center'>
                  2. Enter the 6-digit code from the app
                </Label>
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
                      className='w-12 h-12 text-center text-lg font-semibold'
                    />
                  ))}
                </div>
                {confirmForm.errors.code && (
                  <p className='text-destructive text-xs text-center'>
                    {confirmForm.errors.code}
                  </p>
                )}
              </div>

              <div className='flex justify-center'>
                <Button type='submit' disabled={confirmForm.processing}>
                  Confirm and Enable
                </Button>
              </div>
            </form>
          </div>
        )}

        {user.two_factor_enabled && (
          <div className='space-y-4'>
            <div className='flex items-center justify-between rounded-lg border p-4'>
              <div className='space-y-0.5'>
                <p className='text-sm font-medium flex items-center gap-2'>
                  <ShieldCheck size={16} className='text-green-600' />
                  Enabled
                </p>
                <p className='text-muted-foreground text-xs'>
                  Your account is protected with 2FA.
                </p>
              </div>
              <Button
                variant='destructive'
                size='sm'
                onClick={() => setShowDisableDialog(true)}
              >
                Disable
              </Button>
            </div>

            <Button
              variant='outline'
              onClick={handleRegenerateRecoveryCodes}
              disabled={confirmForm.processing}
            >
              <RefreshCw size={16} className='mr-2' />
              Generate New Recovery Codes
            </Button>
          </div>
        )}

        <Dialog open={showRecoveryCodes} onOpenChange={setShowRecoveryCodes}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Recovery Codes</DialogTitle>
              <DialogDescription>
                Save these recovery codes in a safe place. You can use them to
                access your account if you lose your phone.
              </DialogDescription>
            </DialogHeader>
            <div className='space-y-4'>
              <div className='grid grid-cols-2 gap-2'>
                {recoveryCodes?.map((code: string, index: number) => (
                  <div
                    key={index}
                    className='font-mono text-sm p-2 bg-muted rounded flex items-center justify-between'
                  >
                    <span>{code}</span>
                    <Button
                      size='icon'
                      variant='ghost'
                      className='h-6 w-6'
                      onClick={() => copyToClipboard(code)}
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                ))}
              </div>
              <div className='flex gap-2'>
                <Button
                  onClick={() =>
                    copyToClipboard(recoveryCodes?.join('\n') || '')
                  }
                  variant='outline'
                  className='flex-1'
                >
                  <Copy size={16} className='mr-2' />
                  Copy All Codes
                </Button>
                <Button
                  onClick={downloadRecoveryCodes}
                  variant='outline'
                  className='flex-1'
                >
                  <Download size={16} className='mr-2' />
                  Download Codes
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <Dialog open={showDisableDialog} onOpenChange={setShowDisableDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Disable Two-Factor Authentication</DialogTitle>
              <DialogDescription>
                Enter your password to disable two-factor authentication.
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleDisableTwoFactor} className='space-y-4'>
              <div className='space-y-2'>
                <Label htmlFor='disable_password'>Password</Label>
                <Input
                  id='disable_password'
                  type='password'
                  value={disableForm.data.password}
                  onChange={(e) =>
                    disableForm.setData('password', e.target.value)
                  }
                />
                {disableForm.errors.password && (
                  <p className='text-destructive text-xs'>
                    {disableForm.errors.password}
                  </p>
                )}
              </div>
              <div className='flex gap-2'>
                <Button
                  type='button'
                  variant='outline'
                  onClick={() => setShowDisableDialog(false)}
                  className='flex-1'
                >
                  Cancel
                </Button>
                <Button
                  type='submit'
                  variant='destructive'
                  disabled={disableForm.processing}
                  className='flex-1'
                >
                  Disable 2FA
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      <PasswordGeneratorModal
        isOpen={showPasswordGenerator}
        onClose={() => setShowPasswordGenerator(false)}
        generatedPassword={generatedPassword}
        showPassword={showGeneratedPassword}
        onToggleShowPassword={() => setShowGeneratedPassword(!showGeneratedPassword)}
        onCopyPassword={copyGeneratedPassword}
        onRegeneratePassword={handleRegeneratePassword}
        onUsePassword={handleUseGeneratedPassword}
      />
    </div>
  )
}
