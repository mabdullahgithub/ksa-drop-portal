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
import { Info, Eye, Loader2, Copy, Check, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'

// Placeholder ConnectorSettingsController::show() sends in place of a saved
// encrypted value — the real value never round-trips to the browser.
const MASKED_VALUE = '••••••••'

// Mini info icon + popover explaining how to obtain a given credential and
// where to add it. Mirrors the same pattern used on the J&T and iMile pages.
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

// Encrypted settings are never sent back to the browser, so each one gets an
// on-demand reveal button rather than a local show/hide toggle.
type SecretField = 'password' | 'webhook_username' | 'webhook_password'

// Shared Navix test account, mirroring the fallbacks in config/services.php so
// the form is usable immediately on a fresh install. Not a sandbox — see the
// banner rendered when these are still in use.
const TEST_ACCOUNT_DEFAULTS = {
  company_id: '722',
  email: 'test@navix.com.sa',
  password: 'test@123',
  webhook_username: 'test',
  webhook_password: 'test',
  base_url: 'https://apisv2.logestechs.com/api',
}

export default function LogesTechsSettings() {
  const [connectorId, setConnectorId] = useState<number | null>(null)
  const [settings, setSettings] = useState({ ...TEST_ACCOUNT_DEFAULTS })
  // True while nothing has been saved for this connector, so the form is still
  // showing the shared test account rather than this deployment's own account.
  const [usingTestAccount, setUsingTestAccount] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [loading, setLoading] = useState(true)
  const [revealing, setRevealing] = useState<SecretField | null>(null)
  const [justRevealed, setJustRevealed] = useState<Record<SecretField, boolean>>({
    password: false,
    webhook_username: false,
    webhook_password: false,
  })
  const [copied, setCopied] = useState(false)

  const webhookUrl = `${window.location.origin}/webhooks/logestechs/tracking`

  useEffect(() => {
    init()
  }, [])

  const init = async () => {
    try {
      const list = await axios.get<Connector[]>('/api/connectors')
      const connector = list.data.find((c) => c.key === 'logestechs')
      if (!connector) {
        toast.error('LogesTechs connector is not registered — run the database migrations.')
        setLoading(false)
        return
      }
      setConnectorId(connector.id)

      const res = await axios.get(`/api/connectors/${connector.id}/settings`)
      const data = res.data.settings || {}

      // Anything already saved wins; anything not saved keeps the shared test
      // account default so the form stays usable out of the box.
      setSettings((prev) => ({
        company_id: data.company_id?.value || prev.company_id,
        email: data.email?.value || prev.email,
        password: data.password?.value || prev.password,
        webhook_username: data.webhook_username?.value || prev.webhook_username,
        webhook_password: data.webhook_password?.value || prev.webhook_password,
        base_url: data.base_url?.value || prev.base_url,
      }))

      // Only the account identity matters for the warning — a deployment that
      // has saved its own company/email is no longer on the test account.
      setUsingTestAccount(!data.company_id?.value && !data.email?.value)
    } catch {
      // Settings not yet configured — defaults stay in place.
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
          { key: 'company_id', value: settings.company_id, is_encrypted: false },
          { key: 'email', value: settings.email, is_encrypted: true },
          { key: 'password', value: settings.password, is_encrypted: true },
          { key: 'webhook_username', value: settings.webhook_username, is_encrypted: true },
          { key: 'webhook_password', value: settings.webhook_password, is_encrypted: true },
          { key: 'base_url', value: settings.base_url, is_encrypted: false },
        ],
      })
      setUsingTestAccount(
        settings.company_id === TEST_ACCOUNT_DEFAULTS.company_id &&
          settings.email === TEST_ACCOUNT_DEFAULTS.email
      )
      toast.success('Settings saved successfully!')
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to save settings')
    } finally {
      setSaving(false)
    }
  }

  const revealSecret = async (key: SecretField) => {
    if (!connectorId) return
    setRevealing(key)
    try {
      const res = await axios.post(`/api/connectors/${connectorId}/settings/reveal`, { key })
      setSettings((prev) => ({ ...prev, [key]: res.data.value }))
      setJustRevealed((prev) => ({ ...prev, [key]: true }))
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to reveal value')
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

  const copyWebhookUrl = async () => {
    try {
      await navigator.clipboard.writeText(webhookUrl)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      toast.error('Could not copy — select the URL and copy manually.')
    }
  }

  // Renders a secret field that either offers an on-demand reveal (when the
  // server sent back the mask) or behaves as a normal password input.
  const secretField = (key: SecretField, placeholder: string) =>
    settings[key] === MASKED_VALUE ? (
      <div className='relative rounded-md'>
        <Input
          id={key}
          type='password'
          value={settings[key]}
          onChange={(e) => setSettings({ ...settings, [key]: e.target.value })}
          placeholder={placeholder}
          autoComplete='off'
          className='pe-9'
        />
        <Button
          type='button'
          size='icon'
          variant='ghost'
          disabled={revealing === key}
          className='absolute inset-e-1 top-1/2 h-6 w-6 -translate-y-1/2 rounded-md text-muted-foreground'
          onClick={() => revealSecret(key)}
        >
          {revealing === key ? <Loader2 size={18} className='animate-spin' /> : <Eye size={18} />}
          <span className='sr-only'>Reveal saved value</span>
        </Button>
      </div>
    ) : (
      <PasswordInput
        id={key}
        value={settings[key]}
        onChange={(e) => setSettings({ ...settings, [key]: e.target.value })}
        placeholder={placeholder}
        autoComplete='off'
        data-1p-ignore
        data-lpignore='true'
        data-form-type='other'
        defaultVisible={justRevealed[key]}
      />
    )

  if (loading) {
    return (
      <AuthenticatedLayout>
        <Head title='LogesTechs Settings' />
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
      <Head title='LogesTechs Settings' />

      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='space-y-6'>
          <div>
            <h1 className='text-3xl font-bold tracking-tight'>LogesTechs Settings</h1>
            <p className='text-muted-foreground'>
              Configure your LogesTechs (Navix) account to enable shipment creation, tracking and cancellation
            </p>
          </div>

          {usingTestAccount && (
            <div className='flex items-start gap-2 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300'>
              <AlertTriangle className='mt-0.5 h-4 w-4 shrink-0' />
              <div>
                <span className='font-medium'>Using the shared Navix test account.</span> LogesTechs has no sandbox —
                this account is live, and every shipment created against it dispatches a real driver. Use fake recipient
                details and a zero COD while testing, and cancel test shipments as soon as you&apos;re done. Replace these
                credentials with your own before going live.
              </div>
            </div>
          )}

          <Card>
            <CardHeader>
              <CardTitle>Account Credentials</CardTitle>
              <CardDescription>
                LogesTechs authenticates every request with your account email and password — both are stored encrypted
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='company_id'>Company ID</Label>
                    <FieldInfo title='Company ID'>
                      <p>Sent as the <code>company-id</code> header on every request, and used in the cancel and label URLs.</p>
                      <p><span className='font-medium'>Where to get it:</span> supplied by LogesTechs when your account is set up.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='company_id'
                    value={settings.company_id}
                    onChange={(e) => setSettings({ ...settings, company_id: e.target.value })}
                    placeholder='e.g. 722'
                  />
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='base_url'>Base URL</Label>
                    <FieldInfo title='Base URL'>
                      <p>The LogesTechs API gateway every request is sent to.</p>
                      <p><span className='font-medium'>Production:</span> <code>https://apisv2.logestechs.com/api</code></p>
                      <p>LogesTechs does not provide a separate sandbox host — this is a live system.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='base_url'
                    value={settings.base_url}
                    onChange={(e) => setSettings({ ...settings, base_url: e.target.value })}
                    placeholder='https://apisv2.logestechs.com/api'
                  />
                </div>
              </div>

              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='email'>Account Email</Label>
                    <FieldInfo title='Account Email'>
                      <p>The LogesTechs customer account used to create and cancel shipments.</p>
                      <p>Stored encrypted — leave the masked value untouched to keep the saved address.</p>
                    </FieldInfo>
                  </div>
                  {settings.email === MASKED_VALUE ? (
                    <Input
                      id='email'
                      value={settings.email}
                      onChange={(e) => setSettings({ ...settings, email: e.target.value })}
                      placeholder='name@example.com'
                      autoComplete='off'
                    />
                  ) : (
                    <Input
                      id='email'
                      type='email'
                      value={settings.email}
                      onChange={(e) => setSettings({ ...settings, email: e.target.value })}
                      placeholder='name@example.com'
                      autoComplete='off'
                    />
                  )}
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='password'>Account Password</Label>
                    <FieldInfo title='Account Password'>
                      <p>Sent in the body of every create and cancel request — this is LogesTechs&apos; documented authentication model.</p>
                      <p>Stored encrypted. Use an account scoped to shipping operations where possible.</p>
                    </FieldInfo>
                  </div>
                  {secretField('password', 'Your LogesTechs password')}
                </div>
              </div>

              <div className='flex gap-3 pt-2'>
                <Button onClick={saveSettings} disabled={saving || !connectorId}>
                  {saving ? 'Saving...' : 'Save Settings'}
                </Button>
                <Button variant='outline' onClick={testConnection} disabled={testing || !connectorId}>
                  {testing ? 'Testing...' : 'Test Connection'}
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Status Webhook</CardTitle>
              <CardDescription>
                LogesTechs pushes every status change to this URL. Set the same URL and credentials in your LogesTechs
                portal under <span className='font-medium'>Edit customer webhook</span>.
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='space-y-2'>
                <Label>Webhook URL — paste this into LogesTechs</Label>
                <div className='flex gap-2'>
                  <Input readOnly value={webhookUrl} className='font-mono text-xs' />
                  <Button type='button' variant='outline' size='icon' onClick={copyWebhookUrl} aria-label='Copy webhook URL'>
                    {copied ? <Check className='h-4 w-4 text-green-600' /> : <Copy className='h-4 w-4' />}
                  </Button>
                </div>
              </div>

              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='webhook_username'>Webhook Username</Label>
                    <FieldInfo title='Webhook Username'>
                      <p>LogesTechs echoes this back in every push. We compare it to reject forged updates.</p>
                      <p>It must match exactly what you enter in their webhook panel.</p>
                    </FieldInfo>
                  </div>
                  {secretField('webhook_username', 'Choose a username')}
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='webhook_password'>Webhook Password</Label>
                    <FieldInfo title='Webhook Password'>
                      <p>The shared secret proving a push really came from LogesTechs.</p>
                      <p>Use a long random value — never <code>test</code>. It travels in the request body, so treat it as a bearer token.</p>
                    </FieldInfo>
                  </div>
                  {secretField('webhook_password', 'Choose a strong secret')}
                </div>
              </div>

              {!settings.webhook_username || !settings.webhook_password ? (
                <div className='flex items-start gap-2 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300'>
                  <AlertTriangle className='mt-0.5 h-4 w-4 shrink-0' />
                  <div>
                    <span className='font-medium'>Webhook updates are being rejected.</span> Until both fields are set here
                    and in the LogesTechs portal, every incoming push is refused — shipment statuses will only update when
                    the background sync runs.
                  </div>
                </div>
              ) : (
                settings.webhook_password === TEST_ACCOUNT_DEFAULTS.webhook_password && (
                  <div className='flex items-start gap-2 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300'>
                    <AlertTriangle className='mt-0.5 h-4 w-4 shrink-0' />
                    <div>
                      <span className='font-medium'>This webhook secret is the shared test value.</span> It&apos;s the only
                      thing proving a status update really came from LogesTechs, and it travels in plain text inside every
                      push. Replace it with a long random value here and in the LogesTechs portal before going live.
                    </div>
                  </div>
                )
              )}

              <div className='rounded-md bg-muted p-3 text-xs text-muted-foreground'>
                LogesTechs sends each status change <span className='font-medium'>once, with no retry</span>. If this
                endpoint is unreachable that update is lost, so the scheduled tracking sync stays on as a safety net.
              </div>

              <div className='flex gap-3'>
                <Button onClick={saveSettings} disabled={saving || !connectorId}>
                  {saving ? 'Saving...' : 'Save Settings'}
                </Button>
              </div>
            </CardContent>
          </Card>

          <div className='rounded-md bg-muted p-3 text-xs text-muted-foreground'>
            Warehouses (sender addresses) are shared across couriers — manage them from{' '}
            <a href='/apps/jnt-express' className='underline underline-offset-2'>J&amp;T Settings → Warehouses</a>.
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
