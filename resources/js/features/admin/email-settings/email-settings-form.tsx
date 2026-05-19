import { useForm } from '@inertiajs/react'
import { FormEventHandler, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { CheckCircle2, XCircle, Loader2, Mail, TestTube } from 'lucide-react'

interface EmailSettings {
  is_active: boolean
  driver: string
  host: string
  port: number
  username: string
  password: string
  encryption: string
  from_address: string
  from_name: string
  test_email?: string
  last_tested_at?: string
  test_status?: 'success' | 'failed'
  test_error?: string
}

export function EmailSettingsForm({ settings }: { settings: EmailSettings }) {
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null)

  const { data, setData, put, processing, errors } = useForm({
    is_active: settings?.is_active || false,
    driver: settings?.driver || 'smtp',
    host: settings?.host || '',
    port: settings?.port || 587,
    username: settings?.username || '',
    password: settings?.password || '',
    encryption: settings?.encryption || 'tls',
    from_address: settings?.from_address || '',
    from_name: settings?.from_name || '',
    test_email: settings?.test_email || '',
  })

  const handleSubmit: FormEventHandler = (e) => {
    e.preventDefault()
    put(route('admin.email-settings.update'), {
      onSuccess: () => {
        toast.success('Email settings updated successfully')
      },
      onError: () => {
        toast.error('Failed to update email settings')
      },
    })
  }

  const handleTestConnection = async () => {
    if (!data.test_email) {
      toast.error('Please enter a test email address')
      return
    }

    setTesting(true)
    setTestResult(null)

    try {
      const response = await fetch(route('admin.email-settings.test'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ test_email: data.test_email }),
      })

      const result = await response.json()
      setTestResult(result)

      if (result.success) {
        toast.success('Test email sent successfully!')
      } else {
        toast.error('Test email failed: ' + result.message)
      }
    } catch (error) {
      toast.error('Failed to test connection')
      setTestResult({ success: false, message: 'Network error' })
    } finally {
      setTesting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className='space-y-6'>
      {/* Status Card */}
      <Card>
        <CardHeader>
          <CardTitle className='flex items-center gap-2'>
            <Mail className='h-5 w-5' />
            Email System Status
          </CardTitle>
          <CardDescription>
            Enable or disable the email system globally
          </CardDescription>
        </CardHeader>
        <CardContent className='space-y-4'>
          <div className='flex items-center justify-between rounded-lg border p-4'>
            <div className='space-y-0.5'>
              <Label className='text-base font-medium'>Enable Email System</Label>
              <p className='text-sm text-muted-foreground'>
                When disabled, no emails will be sent from the application
              </p>
            </div>
            <Switch
              checked={data.is_active}
              onCheckedChange={(checked) => setData('is_active', checked)}
            />
          </div>

          {data.is_active && (
            <Alert>
              <CheckCircle2 className='h-4 w-4' />
              <AlertDescription>
                Email system is active. Emails will be sent based on SMTP configuration below.
              </AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>

      {/* SMTP Configuration */}
      <Card>
        <CardHeader>
          <CardTitle>SMTP Configuration</CardTitle>
          <CardDescription>
            Configure your email server settings
          </CardDescription>
        </CardHeader>
        <CardContent className='space-y-4'>
          <div className='grid gap-4 md:grid-cols-2'>
            <div className='space-y-2'>
              <Label htmlFor='driver'>Mail Driver</Label>
              <Select value={data.driver} onValueChange={(value) => setData('driver', value)}>
                <SelectTrigger>
                  <SelectValue placeholder='Select driver' />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='smtp'>SMTP</SelectItem>
                  <SelectItem value='sendmail'>Sendmail</SelectItem>
                  <SelectItem value='mailgun'>Mailgun</SelectItem>
                  <SelectItem value='ses'>Amazon SES</SelectItem>
                  <SelectItem value='postmark'>Postmark</SelectItem>
                  <SelectItem value='log'>Log (Testing)</SelectItem>
                </SelectContent>
              </Select>
              {errors.driver && <p className='text-sm text-destructive'>{errors.driver}</p>}
            </div>

            <div className='space-y-2'>
              <Label htmlFor='host'>SMTP Host</Label>
              <Input
                id='host'
                placeholder='smtp.mailtrap.io'
                value={data.host}
                onChange={(e) => setData('host', e.target.value)}
              />
              {errors.host && <p className='text-sm text-destructive'>{errors.host}</p>}
            </div>
          </div>

          <div className='grid gap-4 md:grid-cols-2'>
            <div className='space-y-2'>
              <Label htmlFor='port'>SMTP Port</Label>
              <Input
                id='port'
                type='number'
                placeholder='587'
                value={data.port}
                onChange={(e) => setData('port', parseInt(e.target.value))}
              />
              {errors.port && <p className='text-sm text-destructive'>{errors.port}</p>}
            </div>

            <div className='space-y-2'>
              <Label htmlFor='encryption'>Encryption</Label>
              <Select value={data.encryption} onValueChange={(value) => setData('encryption', value)}>
                <SelectTrigger>
                  <SelectValue placeholder='Select encryption' />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='tls'>TLS</SelectItem>
                  <SelectItem value='ssl'>SSL</SelectItem>
                  <SelectItem value='none'>None</SelectItem>
                </SelectContent>
              </Select>
              {errors.encryption && <p className='text-sm text-destructive'>{errors.encryption}</p>}
            </div>
          </div>

          <div className='grid gap-4 md:grid-cols-2'>
            <div className='space-y-2'>
              <Label htmlFor='username'>SMTP Username</Label>
              <Input
                id='username'
                placeholder='your-username'
                value={data.username}
                onChange={(e) => setData('username', e.target.value)}
              />
              {errors.username && <p className='text-sm text-destructive'>{errors.username}</p>}
            </div>

            <div className='space-y-2'>
              <Label htmlFor='password'>SMTP Password</Label>
              <Input
                id='password'
                type='password'
                placeholder='••••••••'
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
              />
              {errors.password && <p className='text-sm text-destructive'>{errors.password}</p>}
            </div>
          </div>

          <div className='grid gap-4 md:grid-cols-2'>
            <div className='space-y-2'>
              <Label htmlFor='from_address'>From Email Address</Label>
              <Input
                id='from_address'
                type='email'
                placeholder='noreply@example.com'
                value={data.from_address}
                onChange={(e) => setData('from_address', e.target.value)}
              />
              {errors.from_address && <p className='text-sm text-destructive'>{errors.from_address}</p>}
            </div>

            <div className='space-y-2'>
              <Label htmlFor='from_name'>From Name</Label>
              <Input
                id='from_name'
                placeholder='Your App Name'
                value={data.from_name}
                onChange={(e) => setData('from_name', e.target.value)}
              />
              {errors.from_name && <p className='text-sm text-destructive'>{errors.from_name}</p>}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Test Connection */}
      <Card>
        <CardHeader>
          <CardTitle className='flex items-center gap-2'>
            <TestTube className='h-5 w-5' />
            Test Connection
          </CardTitle>
          <CardDescription>
            Send a test email to verify your SMTP settings
          </CardDescription>
        </CardHeader>
        <CardContent className='space-y-4'>
          <div className='flex gap-4'>
            <div className='flex-1 space-y-2'>
              <Label htmlFor='test_email'>Test Email Address</Label>
              <Input
                id='test_email'
                type='email'
                placeholder='admin@example.com'
                value={data.test_email}
                onChange={(e) => setData('test_email', e.target.value)}
              />
            </div>
            <div className='flex items-end'>
              <Button
                type='button'
                variant='outline'
                onClick={handleTestConnection}
                disabled={testing || !data.test_email}
              >
                {testing ? (
                  <>
                    <Loader2 className='mr-2 h-4 w-4 animate-spin' />
                    Testing...
                  </>
                ) : (
                  <>
                    <Mail className='mr-2 h-4 w-4' />
                    Send Test
                  </>
                )}
              </Button>
            </div>
          </div>

          {testResult && (
            <Alert variant={testResult.success ? 'default' : 'destructive'}>
              {testResult.success ? (
                <CheckCircle2 className='h-4 w-4' />
              ) : (
                <XCircle className='h-4 w-4' />
              )}
              <AlertDescription>{testResult.message}</AlertDescription>
            </Alert>
          )}

          {settings?.last_tested_at && (
            <p className='text-sm text-muted-foreground'>
              Last tested: {new Date(settings.last_tested_at).toLocaleString()}
              {settings.test_status === 'success' && ' ✓ Success'}
              {settings.test_status === 'failed' && ' ✗ Failed'}
            </p>
          )}
        </CardContent>
      </Card>

      <div className='flex justify-end'>
        <Button type='submit' disabled={processing}>
          {processing ? (
            <>
              <Loader2 className='mr-2 h-4 w-4 animate-spin' />
              Saving...
            </>
          ) : (
            'Save Settings'
          )}
        </Button>
      </div>
    </form>
  )
}
