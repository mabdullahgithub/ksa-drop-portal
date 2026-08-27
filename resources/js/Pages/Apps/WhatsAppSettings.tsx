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
    account_sid: '',
    auth_token: '',
    whatsapp_from: '',
    template_sid_order_pending: '',
    template_sid_followup: '',
  })
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [loading, setLoading] = useState(true)
  const [revealingToken, setRevealingToken] = useState(false)
  const [tokenJustRevealed, setTokenJustRevealed] = useState(false)

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
        account_sid: data.account_sid?.value || '',
        auth_token: data.auth_token?.value || '',
        whatsapp_from: data.whatsapp_from?.value || '',
        template_sid_order_pending: data.template_sid_order_pending?.value || '',
        template_sid_followup: data.template_sid_followup?.value || '',
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
          { key: 'account_sid', value: settings.account_sid, is_encrypted: false },
          { key: 'auth_token', value: settings.auth_token, is_encrypted: true },
          { key: 'whatsapp_from', value: settings.whatsapp_from, is_encrypted: false },
          { key: 'template_sid_order_pending', value: settings.template_sid_order_pending, is_encrypted: false },
          { key: 'template_sid_followup', value: settings.template_sid_followup, is_encrypted: false },
        ],
      })
      toast.success('Settings saved successfully!')
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to save settings')
    } finally {
      setSaving(false)
    }
  }

  const revealToken = async () => {
    if (!connectorId) return
    setRevealingToken(true)
    try {
      const res = await axios.post(`/api/connectors/${connectorId}/settings/reveal`, { key: 'auth_token' })
      setSettings((prev) => ({ ...prev, auth_token: res.data.value }))
      setTokenJustRevealed(true)
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to reveal auth token')
    } finally {
      setRevealingToken(false)
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
              Confirm orders and verify delivery addresses over WhatsApp, via Twilio
            </p>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Twilio Credentials</CardTitle>
              <CardDescription>
                From the Twilio Console home page — Account SID is shown at the top, the Auth Token behind
                &ldquo;API keys and Auth tokens&rdquo;
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='account_sid'>Account SID</Label>
                    <FieldInfo title='Account SID'>
                      <p>Public identifier for your Twilio account. Always starts with <code>AC</code>.</p>
                      <p><span className='font-medium'>Where to get it:</span> console.twilio.com → Account home.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='account_sid'
                    value={settings.account_sid}
                    onChange={(e) => setSettings({ ...settings, account_sid: e.target.value })}
                    placeholder='ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
                  />
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='auth_token'>Auth Token</Label>
                    <FieldInfo title='Auth Token'>
                      <p>The secret paired with your Account SID. Also used to verify the signature on every
                        webhook Twilio sends us.</p>
                      <p><span className='font-medium'>Where to get it:</span> console.twilio.com → Account home →
                        API keys and Auth tokens. Stored encrypted — leave the masked value untouched to keep
                        the saved token.</p>
                    </FieldInfo>
                  </div>
                  {settings.auth_token === MASKED_VALUE ? (
                    <div className='relative rounded-md'>
                      <Input
                        id='auth_token'
                        type='password'
                        value={settings.auth_token}
                        onChange={(e) => setSettings({ ...settings, auth_token: e.target.value })}
                        placeholder='Your Twilio auth token'
                        autoComplete='off'
                        className='pe-9'
                      />
                      <Button
                        type='button'
                        size='icon'
                        variant='ghost'
                        disabled={revealingToken}
                        className='absolute inset-e-1 top-1/2 h-6 w-6 -translate-y-1/2 rounded-md text-muted-foreground'
                        onClick={revealToken}
                      >
                        {revealingToken ? <Loader2 size={18} className='animate-spin' /> : <Eye size={18} />}
                        <span className='sr-only'>Reveal saved auth token</span>
                      </Button>
                    </div>
                  ) : (
                    <PasswordInput
                      id='auth_token'
                      value={settings.auth_token}
                      onChange={(e) => setSettings({ ...settings, auth_token: e.target.value })}
                      placeholder='Your Twilio auth token'
                      autoComplete='off'
                      data-1p-ignore
                      data-lpignore='true'
                      data-form-type='other'
                      defaultVisible={tokenJustRevealed}
                    />
                  )}
                </div>
              </div>

              <div className='space-y-2'>
                <div className='flex items-center gap-1.5'>
                  <Label htmlFor='whatsapp_from'>WhatsApp From Number</Label>
                  <FieldInfo title='WhatsApp From Number'>
                    <p>The sender number messages go out from. Must include Twilio&apos;s
                      <code> whatsapp:</code> prefix.</p>
                    <p><span className='font-medium'>Sandbox:</span> the number shown on Twilio&apos;s
                      &ldquo;Try out WhatsApp&rdquo; page, e.g. <code>whatsapp:+17372212163</code>.</p>
                    <p><span className='font-medium'>Production:</span> your approved WhatsApp Sender number.</p>
                  </FieldInfo>
                </div>
                <Input
                  id='whatsapp_from'
                  value={settings.whatsapp_from}
                  onChange={(e) => setSettings({ ...settings, whatsapp_from: e.target.value })}
                  placeholder='whatsapp:+17372212163'
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Message Templates</CardTitle>
              <CardDescription>
                Meta-approved Content template SIDs. Leave both blank while testing in the sandbox — plain-text
                messages are sent instead
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='template_sid_order_pending'>Initial Message Template SID</Label>
                    <FieldInfo title='Initial Message Template'>
                      <p>Sent as soon as an agent marks a call &ldquo;No Answer&rdquo;. Asks the customer to
                        confirm the order or correct the address.</p>
                      <p><span className='font-medium'>Where to get it:</span> Twilio Console → Content Template
                        Builder. Starts with <code>HX</code>.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='template_sid_order_pending'
                    value={settings.template_sid_order_pending}
                    onChange={(e) => setSettings({ ...settings, template_sid_order_pending: e.target.value })}
                    placeholder='HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
                  />
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='template_sid_followup'>Follow-up Template SID</Label>
                    <FieldInfo title='Follow-up Template'>
                      <p>Sent 24 hours later if the customer never replied. After a further 24 hours of silence
                        the order is tagged <code>Graveyard</code>.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='template_sid_followup'
                    value={settings.template_sid_followup}
                    onChange={(e) => setSettings({ ...settings, template_sid_followup: e.target.value })}
                    placeholder='HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
                  />
                </div>
              </div>

              <div className='rounded-md bg-muted p-3 text-xs text-muted-foreground'>
                Templates use positional variables: <code>{'{{1}}'}</code> customer name,{' '}
                <code>{'{{2}}'}</code> order number, <code>{'{{3}}'}</code> delivery address.
                Submit them to Meta under the <span className='font-medium'>Utility</span> category.
                <br />
                Do <span className='font-medium'>not</span> put a company name in the copy — the
                customer ordered from the client&apos;s store, and WhatsApp already shows the
                sending business profile in the chat header.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Webhook URLs</CardTitle>
              <CardDescription>
                Register both in the Twilio Console so replies and delivery receipts reach the portal
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-3'>
              {[
                {
                  label: 'Incoming replies',
                  hint: 'Sandbox settings → "When a message comes in"',
                  url: `${origin}/webhooks/twilio/whatsapp`,
                },
                {
                  label: 'Delivery & read receipts',
                  hint: 'Sandbox settings → "Status callback URL"',
                  url: `${origin}/webhooks/twilio/whatsapp/status`,
                },
              ].map((row) => (
                <div key={row.url} className='space-y-1'>
                  <div className='flex items-baseline gap-2'>
                    <Label className='text-xs'>{row.label}</Label>
                    <span className='text-xs text-muted-foreground'>{row.hint}</span>
                  </div>
                  <div className='flex gap-2'>
                    <Input readOnly value={row.url} className='font-mono text-xs' />
                    <Button type='button' variant='outline' onClick={() => copy(row.url)}>
                      Copy
                    </Button>
                  </div>
                </div>
              ))}

              <div className='rounded-md bg-muted p-3 text-xs text-muted-foreground'>
                Both URLs must be publicly reachable over HTTPS — use an ngrok tunnel while developing locally.
                Twilio signs every request, and the portal rejects any that fails signature verification.
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
