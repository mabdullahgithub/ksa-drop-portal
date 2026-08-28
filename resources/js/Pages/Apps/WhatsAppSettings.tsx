import { Head } from '@inertiajs/react'
import { useState, useEffect } from 'react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { PasswordInput } from '@/components/password-input'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Info, Eye, Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'

// Placeholder ConnectorSettingsController::show() sends in place of a saved
// encrypted value — the real value never round-trips to the browser.
const MASKED_VALUE = '••••••••'

function FieldInfo({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type='button'
          aria-label={`How to get ${title}`}
          className='text-muted-foreground transition-colors hover:text-foreground'
        >
          <Info className='h-3.5 w-3.5' />
        </button>
      </PopoverTrigger>
      <PopoverContent align='start' className='w-80 text-sm'>
        <p className='mb-1 font-semibold'>{title}</p>
        <div className='space-y-1 text-xs text-muted-foreground'>{children}</div>
      </PopoverContent>
    </Popover>
  )
}

interface Connector {
  id: number
  key: string
  name: string
}

export default function WhatsAppSettings() {
  const [connectorId, setConnectorId] = useState<number | null>(null)
  const [settings, setSettings] = useState({
    phone_number_id: '',
    waba_id: '',
    access_token: '',
    app_secret: '',
    webhook_verify_token: '',
    template_language: 'en_US',
    template_name_order_pending: '',
    template_name_followup: '',
  })
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [loading, setLoading] = useState(true)
  const [revealing, setRevealing] = useState<string | null>(null)
  const [justRevealed, setJustRevealed] = useState<Record<string, boolean>>({})

  const origin = typeof window !== 'undefined' ? window.location.origin : ''

  useEffect(() => {
    init()
  }, [])

  const init = async () => {
    try {
      const list = await axios.get<Connector[]>('/api/connectors')
      const connector = list.data.find((c) => c.key === 'whatsapp')
      if (!connector) {
        toast.error('WhatsApp connector is not registered — run the database migrations.')
        setLoading(false)
        return
      }
      setConnectorId(connector.id)

      const res = await axios.get(`/api/connectors/${connector.id}/settings`)
      const data = res.data.settings || {}
      setSettings((prev) => ({
        ...prev,
        phone_number_id: data.phone_number_id?.value || '',
        waba_id: data.waba_id?.value || '',
        access_token: data.access_token?.value || '',
        app_secret: data.app_secret?.value || '',
        webhook_verify_token: data.webhook_verify_token?.value || '',
        template_language: data.template_language?.value || 'en_US',
        template_name_order_pending: data.template_name_order_pending?.value || '',
        template_name_followup: data.template_name_followup?.value || '',
      }))
    } catch {
      // Settings not yet configured
    } finally {
      setLoading(false)
    }
  }

  const saveSettings = async () => {
    if (!connectorId) return
    setSaving(true)
    try {
      await axios.put(`/api/connectors/${connectorId}/settings`, {
        settings: [
          { key: 'phone_number_id', value: settings.phone_number_id, is_encrypted: false },
          { key: 'waba_id', value: settings.waba_id, is_encrypted: false },
          { key: 'access_token', value: settings.access_token, is_encrypted: true },
          { key: 'app_secret', value: settings.app_secret, is_encrypted: true },
          { key: 'webhook_verify_token', value: settings.webhook_verify_token, is_encrypted: false },
          { key: 'template_language', value: settings.template_language, is_encrypted: false },
          { key: 'template_name_order_pending', value: settings.template_name_order_pending, is_encrypted: false },
          { key: 'template_name_followup', value: settings.template_name_followup, is_encrypted: false },
        ],
      })
      toast.success('Settings saved successfully!')
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to save settings')
    } finally {
      setSaving(false)
    }
  }

  const revealSecret = async (key: 'access_token' | 'app_secret') => {
    if (!connectorId) return
    setRevealing(key)
    try {
      const res = await axios.post(`/api/connectors/${connectorId}/settings/reveal`, { key })
      setSettings((prev) => ({ ...prev, [key]: res.data.value }))
      setJustRevealed((prev) => ({ ...prev, [key]: true }))
    } catch (err: any) {
      toast.error(err.response?.data?.message || `Failed to reveal ${key.replace('_', ' ')}`)
    } finally {
      setRevealing(null)
    }
  }

  const testConnection = async () => {
    if (!connectorId) return
    setTesting(true)
    try {
      const res = await axios.post(`/api/connectors/${connectorId}/test`)
      if (res.data.success) {
        toast.success(res.data.message)
      } else {
        toast.error(res.data.message)
      }
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Connection test failed')
    } finally {
      setTesting(false)
    }
  }

  const copy = (value: string) => {
    navigator.clipboard.writeText(value)
    toast.success('Copied to clipboard')
  }

  if (loading) {
    return (
      <AuthenticatedLayout>
        <Head title='WhatsApp Settings' />
        <Header>
          <Search className='me-auto' />
          <ThemeSwitch />
          <NotificationsDropdown />
          <ProfileDropdown />
        </Header>
        <Main>
          <div className='flex items-center justify-center py-12'>
            <p className='text-muted-foreground'>Loading...</p>
          </div>
        </Main>
      </AuthenticatedLayout>
    )
  }

  return (
    <AuthenticatedLayout>
      <Head title='WhatsApp Settings' />

      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='space-y-6'>
          <div>
            <h1 className='text-3xl font-bold tracking-tight'>WhatsApp Settings</h1>
            <p className='text-muted-foreground'>
              Confirm orders and verify delivery addresses over WhatsApp, via the Meta Cloud API
            </p>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Meta Credentials</CardTitle>
              <CardDescription>
                From the Meta App Dashboard — IDs and token under WhatsApp &rarr; API Setup, the App Secret
                under App settings &rarr; Basic
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='phone_number_id'>Phone Number ID</Label>
                    <FieldInfo title='Phone Number ID'>
                      <p>Numeric ID of the WhatsApp sender. Not the phone number itself &mdash; every send
                        is addressed to this ID.</p>
                      <p><span className='font-medium'>Where to get it:</span> developers.facebook.com &rarr;
                        your app &rarr; WhatsApp &rarr; API Setup.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='phone_number_id'
                    value={settings.phone_number_id}
                    onChange={(e) => setSettings({ ...settings, phone_number_id: e.target.value })}
                    placeholder='123456789012345'
                  />
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='waba_id'>WhatsApp Business Account ID</Label>
                    <FieldInfo title='WhatsApp Business Account ID'>
                      <p>Identifies the WABA that owns the number and its message templates.</p>
                      <p><span className='font-medium'>Where to get it:</span> same API Setup page, directly
                        below the Phone Number ID.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='waba_id'
                    value={settings.waba_id}
                    onChange={(e) => setSettings({ ...settings, waba_id: e.target.value })}
                    placeholder='123456789012345'
                  />
                </div>
              </div>

              <div className='space-y-2'>
                <div className='flex items-center gap-1.5'>
                  <Label htmlFor='access_token'>Access Token</Label>
                  <FieldInfo title='Access Token'>
                    <p>Bearer token for every Graph API call.</p>
                    <p><span className='font-medium'>Important:</span> the token shown on the API Setup page
                      is temporary and expires in 24 hours. For production, create a System User in Business
                      Settings and generate a permanent token with the
                      <code> whatsapp_business_messaging</code> and <code> whatsapp_business_management</code>
                      permissions.</p>
                    <p>Stored encrypted &mdash; leave the masked value untouched to keep the saved token.</p>
                  </FieldInfo>
                </div>
                {settings.access_token === MASKED_VALUE ? (
                  <div className='relative rounded-md'>
                    <Input
                      id='access_token'
                      type='password'
                      value={settings.access_token}
                      onChange={(e) => setSettings({ ...settings, access_token: e.target.value })}
                      placeholder='EAAG...'
                      autoComplete='off'
                      className='pe-9'
                    />
                    <Button
                      type='button'
                      size='icon'
                      variant='ghost'
                      disabled={revealing === 'access_token'}
                      className='absolute inset-e-1 top-1/2 h-6 w-6 -translate-y-1/2 rounded-md text-muted-foreground'
                      onClick={() => revealSecret('access_token')}
                    >
                      {revealing === 'access_token' ? <Loader2 size={18} className='animate-spin' /> : <Eye size={18} />}
                      <span className='sr-only'>Reveal saved access token</span>
                    </Button>
                  </div>
                ) : (
                  <PasswordInput
                    id='access_token'
                    value={settings.access_token}
                    onChange={(e) => setSettings({ ...settings, access_token: e.target.value })}
                    placeholder='EAAG...'
                    autoComplete='off'
                    data-1p-ignore
                    data-lpignore='true'
                    data-form-type='other'
                    defaultVisible={justRevealed.access_token}
                  />
                )}
              </div>

              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='app_secret'>App Secret</Label>
                    <FieldInfo title='App Secret'>
                      <p>Signs every webhook Meta sends us. The portal rejects any request whose
                        <code> X-Hub-Signature-256</code> does not verify against this.</p>
                      <p><span className='font-medium'>Where to get it:</span> App Dashboard &rarr; App
                        settings &rarr; Basic &rarr; App Secret (click Show).</p>
                      <p>Without it, no replies or delivery receipts are accepted at all.</p>
                    </FieldInfo>
                  </div>
                  {settings.app_secret === MASKED_VALUE ? (
                    <div className='relative rounded-md'>
                      <Input
                        id='app_secret'
                        type='password'
                        value={settings.app_secret}
                        onChange={(e) => setSettings({ ...settings, app_secret: e.target.value })}
                        autoComplete='off'
                        className='pe-9'
                      />
                      <Button
                        type='button'
                        size='icon'
                        variant='ghost'
                        disabled={revealing === 'app_secret'}
                        className='absolute inset-e-1 top-1/2 h-6 w-6 -translate-y-1/2 rounded-md text-muted-foreground'
                        onClick={() => revealSecret('app_secret')}
                      >
                        {revealing === 'app_secret' ? <Loader2 size={18} className='animate-spin' /> : <Eye size={18} />}
                        <span className='sr-only'>Reveal saved app secret</span>
                      </Button>
                    </div>
                  ) : (
                    <PasswordInput
                      id='app_secret'
                      value={settings.app_secret}
                      onChange={(e) => setSettings({ ...settings, app_secret: e.target.value })}
                      autoComplete='off'
                      data-1p-ignore
                      data-lpignore='true'
                      data-form-type='other'
                      defaultVisible={justRevealed.app_secret}
                    />
                  )}
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='webhook_verify_token'>Webhook Verify Token</Label>
                    <FieldInfo title='Webhook Verify Token'>
                      <p>A string you invent. Meta echoes it back once when you save the webhook URL, and the
                        portal only completes the handshake if the two match.</p>
                      <p>Paste the same value here and in the Meta dashboard webhook config.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='webhook_verify_token'
                    value={settings.webhook_verify_token}
                    onChange={(e) => setSettings({ ...settings, webhook_verify_token: e.target.value })}
                    placeholder='any random string'
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Message Templates</CardTitle>
              <CardDescription>
                Names of templates approved in WhatsApp Manager. Both are required &mdash; Meta rejects
                business-initiated messages that are not an approved template
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='template_name_order_pending'>Initial Message Template</Label>
                    <FieldInfo title='Initial Message Template'>
                      <p>Sent as soon as an agent marks a call &ldquo;No Answer&rdquo;. Asks the customer to
                        confirm the order or correct the address.</p>
                      <p><span className='font-medium'>Where to get it:</span> business.facebook.com &rarr;
                        WhatsApp Manager &rarr; Message Templates. Use the template <em>name</em>, lowercase
                        with underscores.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='template_name_order_pending'
                    value={settings.template_name_order_pending}
                    onChange={(e) => setSettings({ ...settings, template_name_order_pending: e.target.value })}
                    placeholder='order_pending'
                  />
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='template_name_followup'>Follow-up Template</Label>
                    <FieldInfo title='Follow-up Template'>
                      <p>Sent 24 hours later if the customer never replied. After a further 24 hours of silence
                        the order is tagged <code>Graveyard</code>.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='template_name_followup'
                    value={settings.template_name_followup}
                    onChange={(e) => setSettings({ ...settings, template_name_followup: e.target.value })}
                    placeholder='followup'
                  />
                </div>
              </div>

              <div className='space-y-2 md:max-w-xs'>
                <div className='flex items-center gap-1.5'>
                  <Label htmlFor='template_language'>Template Language</Label>
                  <FieldInfo title='Template Language'>
                    <p>Locale code of the approved templates, e.g. <code>ar</code> for Arabic or
                      <code> en_US</code> for English. Must match exactly or the send is rejected.</p>
                  </FieldInfo>
                </div>
                <Input
                  id='template_language'
                  value={settings.template_language}
                  onChange={(e) => setSettings({ ...settings, template_language: e.target.value })}
                  placeholder='en_US'
                />
              </div>

              <div className='rounded-md bg-muted p-3 text-xs text-muted-foreground'>
                Templates use positional variables: <code>{'{{1}}'}</code> customer name,{' '}
                <code>{'{{2}}'}</code> order number, <code>{'{{3}}'}</code> delivery address.
                Submit them under the <span className='font-medium'>Utility</span> category.
                <br />
                Do <span className='font-medium'>not</span> put a company name in the copy &mdash; the
                customer ordered from the client&apos;s store, and WhatsApp already shows the
                sending business profile in the chat header.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Webhook URL</CardTitle>
              <CardDescription>
                One URL handles both inbound replies and delivery receipts. Register it in the Meta App
                Dashboard under WhatsApp &rarr; Configuration
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-3'>
              <div className='space-y-1'>
                <div className='flex items-baseline gap-2'>
                  <Label className='text-xs'>Callback URL</Label>
                  <span className='text-xs text-muted-foreground'>
                    Paste the Verify Token above into the field beside it
                  </span>
                </div>
                <div className='flex gap-2'>
                  <Input readOnly value={`${origin}/webhooks/whatsapp`} className='font-mono text-xs' />
                  <Button type='button' variant='outline' onClick={() => copy(`${origin}/webhooks/whatsapp`)}>
                    Copy
                  </Button>
                </div>
              </div>

              <div className='rounded-md bg-muted p-3 text-xs text-muted-foreground'>
                After saving, subscribe the app to the <code>messages</code> webhook field &mdash; nothing
                arrives until you do.
                <br />
                The URL must be publicly reachable over HTTPS; use an ngrok tunnel while developing locally.
                Meta signs every request and the portal rejects any that fails signature verification.
              </div>
            </CardContent>
          </Card>

          <div className='flex gap-3'>
            <Button onClick={saveSettings} disabled={saving || !connectorId}>
              {saving ? 'Saving...' : 'Save Settings'}
            </Button>
            <Button variant='outline' onClick={testConnection} disabled={testing || !connectorId}>
              {testing ? 'Testing...' : 'Test Connection'}
            </Button>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
