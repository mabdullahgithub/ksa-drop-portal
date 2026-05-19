import { useForm, usePage } from '@inertiajs/react'
import { FormEventHandler, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AlertCircle, CheckCircle2, Loader2, Mail } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'

export function EmailSmtpForm() {
  const pageProps = usePage().props as any
  const emailSettings = pageProps.emailSettings

  // Debug: log everything
  console.log('EmailSmtpForm - Full props:', pageProps)
  console.log('EmailSmtpForm - emailSettings:', emailSettings)

  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null)
  const [testing, setTesting] = useState(false)

  const { data, setData, put, processing, errors } = useForm({
    is_active: emailSettings?.is_active ?? false,
    driver: emailSettings?.driver ?? 'smtp',
    host: emailSettings?.host ?? '',
    port: emailSettings?.port ?? 587,
    username: emailSettings?.username ?? '',
    password: '',
    encryption: emailSettings?.encryption ?? 'tls',
    from_address: emailSettings?.from_address ?? '',
    from_name: emailSettings?.from_name ?? '',
  })

  console.log('EmailSmtpForm - form data:', data)

  const handleSubmit: FormEventHandler = (e) => {
    e.preventDefault()
    put(route('settings.email.smtp.update'), {
      onSuccess: () => {
        toast.success('SMTP settings updated successfully')
        setTestResult(null)
      },
      onError: () => {
        toast.error('Failed to update SMTP settings')
      },
    })
  }

  const handleTest = async () => {
    const testEmail = prompt('Enter email address to send test email:')
    if (!testEmail) return

    setTesting(true)
    setTestResult(null)

    try {
      const response = await fetch(route('admin.email-settings.test'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ test_email: testEmail }),
      })

      const result = await response.json()
      setTestResult(result)

      if (result.success) {
        toast.success(result.message)
      } else {
        toast.error(result.message)
      }
    } catch (error) {
      setTestResult({
        success: false,
        message: 'Failed to test connection. Please try again.',
      })
      toast.error('Failed to test connection')
    } finally {
      setTesting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className='space-y-6'>
      {/* Email System Status */}
      <div className='flex items-center justify-between rounded-lg border p-4'>
        <div className='space-y-0.5'>
          <Label className='text-base font-medium'>Email System Status</Label>
          <p className='text-sm text-muted-foreground'>
            Enable or disable the email system
          </p>
        </div>
        <Switch
          checked={data.is_active}
          onCheckedChange={(checked) => setData('is_active', checked)}
        />
      </div>

      {/* Driver Selection */}
      <div className='space-y-2'>
        <Label htmlFor='driver'>Mail Driver</Label>
        <Select value={data.driver} onValueChange={(value) => setData('driver', value)}>
          <SelectTrigger id='driver'>
            <SelectValue placeholder='Select driver' />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='smtp'>SMTP</SelectItem>
            <SelectItem value='sendmail'>Sendmail</SelectItem>
            <SelectItem value='mailgun'>Mailgun</SelectItem>
            <SelectItem value='ses'>Amazon SES</SelectItem>
            <SelectItem value='postmark'>Postmark</SelectItem>
            <SelectItem value='log'>Log (Development)</SelectItem>
          </SelectContent>
        </Select>
        {errors.driver && (
          <p className='text-sm text-destructive'>{errors.driver}</p>
        )}
      </div>

      {/* SMTP Configuration */}
      {data.driver === 'smtp' && (
        <>
          <div className='grid gap-4 sm:grid-cols-2'>
            <div className='space-y-2'>
              <Label htmlFor='host'>SMTP Host</Label>
              <Input
                id='host'
                value={data.host}
                onChange={(e) => setData('host', e.target.value)}
                placeholder='smtp.example.com'
              />
              {errors.host && (
                <p className='text-sm text-destructive'>{errors.host}</p>
              )}
            </div>

            <div className='space-y-2'>
              <Label htmlFor='port'>SMTP Port</Label>
              <Input
                id='port'
                type='number'
                value={data.port}
                onChange={(e) => setData('port', parseInt(e.target.value))}
                placeholder='587'
              />
              {errors.port && (
                <p className='text-sm text-destructive'>{errors.port}</p>
              )}
            </div>
          </div>

          <div className='space-y-2'>
            <Label htmlFor='encryption'>Encryption</Label>
            <Select value={data.encryption} onValueChange={(value) => setData('encryption', value)}>
              <SelectTrigger id='encryption'>
                <SelectValue placeholder='Select encryption' />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value='tls'>TLS</SelectItem>
                <SelectItem value='ssl'>SSL</SelectItem>
                <SelectItem value='none'>None</SelectItem>
              </SelectContent>
            </Select>
            {errors.encryption && (
              <p className='text-sm text-destructive'>{errors.encryption}</p>
            )}
          </div>

          <div className='space-y-2'>
            <Label htmlFor='username'>SMTP Username</Label>
            <Input
              id='username'
              value={data.username}
              onChange={(e) => setData('username', e.target.value)}
              placeholder='your@email.com'
            />
            {errors.username && (
              <p className='text-sm text-destructive'>{errors.username}</p>
            )}
          </div>

          <div className='space-y-2'>
            <Label htmlFor='password'>SMTP Password</Label>
            <Input
              id='password'
              type='password'
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              placeholder={emailSettings?.password_status === 'set' ? '••••••••' : 'Enter password'}
            />
            <p className='text-xs text-muted-foreground'>
              {emailSettings?.password_status === 'set'
                ? 'Leave empty to keep existing password'
                : 'Enter your SMTP password'}
            </p>
            {errors.password && (
              <p className='text-sm text-destructive'>{errors.password}</p>
            )}
          </div>
        </>
      )}

      {/* From Address */}
      <div className='space-y-2'>
        <Label htmlFor='from_address'>From Email Address</Label>
        <Input
          id='from_address'
          type='email'
          value={data.from_address}
          onChange={(e) => setData('from_address', e.target.value)}
          placeholder='noreply@example.com'
        />
        {errors.from_address && (
          <p className='text-sm text-destructive'>{errors.from_address}</p>
        )}
      </div>

      <div className='space-y-2'>
        <Label htmlFor='from_name'>From Name</Label>
        <Input
          id='from_name'
          value={data.from_name}
          onChange={(e) => setData('from_name', e.target.value)}
          placeholder='My Application'
        />
        {errors.from_name && (
          <p className='text-sm text-destructive'>{errors.from_name}</p>
        )}
      </div>

      {/* Test Result Alert */}
      {testResult && (
        <Alert variant={testResult.success ? 'default' : 'destructive'}>
          {testResult.success ? (
            <CheckCircle2 className='h-4 w-4' />
          ) : (
            <AlertCircle className='h-4 w-4' />
          )}
          <AlertDescription>{testResult.message}</AlertDescription>
        </Alert>
      )}

      {/* Action Buttons */}
      <div className='flex gap-2'>
        <Button type='submit' disabled={processing}>
          {processing ? 'Saving...' : 'Save Configuration'}
        </Button>

        {emailSettings?.id && (
          <Button
            type='button'
            variant='outline'
            onClick={handleTest}
            disabled={testing}
          >
            {testing ? (
              <>
                <Loader2 className='mr-2 h-4 w-4 animate-spin' />
                Testing...
              </>
            ) : (
              <>
                <Mail className='mr-2 h-4 w-4' />
                Test Connection
              </>
            )}
          </Button>
        )}
      </div>

      <p className='text-sm text-muted-foreground'>
        💡 <strong>Tip:</strong> Use{' '}
        <a
          href='https://mailtrap.io'
          target='_blank'
          rel='noopener noreferrer'
          className='text-primary hover:underline'
        >
          Mailtrap
        </a>{' '}
        for testing emails in development.
      </p>
    </form>
  )
}
