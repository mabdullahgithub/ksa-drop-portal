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
import { Info } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'

// Mini info icon + popover explaining how to obtain a given credential and
// where to add it. Mirrors the same pattern used on the J&T settings page.
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

export default function ImileSettings() {
  const [connectorId, setConnectorId] = useState<number | null>(null)
  const [settings, setSettings] = useState({
    customer_id: '',
    api_key: '',
    base_url: 'https://openapi.imile.com',
    time_zone: '+3',
  })
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    init()
  }, [])

  const init = async () => {
    try {
      const list = await axios.get<Connector[]>('/api/connectors')
      const connector = list.data.find((c) => c.key === 'imile')
      if (!connector) {
        toast.error('iMile connector is not registered — run the database migrations.')
        setLoading(false)
        return
      }
      setConnectorId(connector.id)

      const res = await axios.get(`/api/connectors/${connector.id}/settings`)
      const data = res.data.settings || {}
      setSettings((prev) => ({
        ...prev,
        customer_id: data.customer_id?.value || '',
        api_key: data.api_key?.value || '',
        base_url: data.base_url?.value || 'https://openapi.imile.com',
        time_zone: data.time_zone?.value || '+3',
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
          { key: 'customer_id', value: settings.customer_id, is_encrypted: false },
          { key: 'api_key', value: settings.api_key, is_encrypted: true },
          { key: 'base_url', value: settings.base_url, is_encrypted: false },
          { key: 'time_zone', value: settings.time_zone, is_encrypted: false },
        ],
      })
      toast.success('Settings saved successfully!')
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to save settings')
    } finally {
      setSaving(false)
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

  if (loading) {
    return (
      <AuthenticatedLayout>
        <Head title='iMile Settings' />
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
      <Head title='iMile Settings' />

      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='space-y-6'>
          <div>
            <h1 className='text-3xl font-bold tracking-tight'>iMile Settings</h1>
            <p className='text-muted-foreground'>
              Configure your iMile API credentials to enable shipment creation, tracking and cancellation
            </p>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>API Configuration</CardTitle>
              <CardDescription>
                Enter your iMile CustomerID and APIKey — contact your iMile account manager if you don&apos;t have these yet
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='customer_id'>Customer ID</Label>
                    <FieldInfo title='Customer ID (customerId)'>
                      <p>Your iMile client code. Sent on every request to identify your account.</p>
                      <p><span className='font-medium'>Where to get it:</span> issued by your iMile account manager together with your APIKey.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='customer_id'
                    value={settings.customer_id}
                    onChange={(e) => setSettings({ ...settings, customer_id: e.target.value })}
                    placeholder='e.g. C2102192601'
                  />
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='api_key'>API Key</Label>
                    <FieldInfo title='API Key'>
                      <p>Used as the request signature (<code>sign</code>) in SimpleKey mode — the only signing mode this integration uses.</p>
                      <p><span className='font-medium'>Where to get it:</span> issued together with your Customer ID by iMile. Stored encrypted — leave the masked value untouched to keep the saved key.</p>
                    </FieldInfo>
                  </div>
                  <PasswordInput
                    id='api_key'
                    value={settings.api_key}
                    onChange={(e) => setSettings({ ...settings, api_key: e.target.value })}
                    placeholder='Your iMile API key'
                  />
                </div>
              </div>

              <div className='grid gap-4 md:grid-cols-2'>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='base_url'>Base URL</Label>
                    <FieldInfo title='Base URL'>
                      <p>The iMile OpenAPI gateway every request is sent to.</p>
                      <p><span className='font-medium'>Production:</span> <code>https://openapi.imile.com</code></p>
                      <p><span className='font-medium'>Testing / sandbox:</span> <code>https://openapi.52imile.cn</code></p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='base_url'
                    value={settings.base_url}
                    onChange={(e) => setSettings({ ...settings, base_url: e.target.value })}
                    placeholder='https://openapi.imile.com'
                  />
                </div>
                <div className='space-y-2'>
                  <div className='flex items-center gap-1.5'>
                    <Label htmlFor='time_zone'>Time Zone</Label>
                    <FieldInfo title='Time Zone (timeZone)'>
                      <p>Sent with every request alongside the timestamp. Use <code>+3</code> for KSA, <code>+4</code> for UAE.</p>
                    </FieldInfo>
                  </div>
                  <Input
                    id='time_zone'
                    value={settings.time_zone}
                    onChange={(e) => setSettings({ ...settings, time_zone: e.target.value })}
                    placeholder='+3'
                  />
                </div>
              </div>

              <div className='rounded-md bg-muted p-3 text-xs text-muted-foreground'>
                Warehouses (sender addresses) are shared across couriers — manage them from{' '}
                <a href='/apps/jnt-express' className='underline underline-offset-2'>J&amp;T Settings → Warehouses</a>.
              </div>

              <div className='flex gap-3 pt-4'>
                <Button onClick={saveSettings} disabled={saving || !connectorId}>
                  {saving ? 'Saving...' : 'Save Settings'}
                </Button>
                <Button variant='outline' onClick={testConnection} disabled={testing || !connectorId}>
                  {testing ? 'Testing...' : 'Test Connection'}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
