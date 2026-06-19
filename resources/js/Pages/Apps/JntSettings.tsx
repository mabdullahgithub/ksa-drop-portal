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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Info } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'

// Mini info icon + popover explaining how to obtain a given credential and where
// to add it. Used next to each J&T API credential field.
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

interface Warehouse {
  id: number
  name: string
  contact_name: string
  phone: string
  province: string
  city: string
  area: string | null
  address: string
  post_code: string | null
  country_code: string
  is_default: boolean
  is_active: boolean
}

export default function JntSettings() {
  const [settings, setSettings] = useState({
    api_account: '',
    private_key: '',
    customer_code: '',
    customer_password: '',
    sandbox_uuid: '',
    base_url: 'https://openapi.jtjms-sa.com',
    default_weight: '0.5',
    default_service_type: '02',
  })
  const [warehouses, setWarehouses] = useState<Warehouse[]>([])
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [loading, setLoading] = useState(true)
  const [warehouseForm, setWarehouseForm] = useState({
    name: '',
    contact_name: '',
    phone: '',
    province: '',
    city: '',
    area: '',
    address: '',
    post_code: '',
    country_code: 'SA',
    is_default: false,
  })
  const [editingWarehouse, setEditingWarehouse] = useState<number | null>(null)

  useEffect(() => {
    loadSettings()
    loadWarehouses()
  }, [])

  const loadSettings = async () => {
    try {
      const res = await axios.get('/api/connectors/2/settings')
      const data = res.data.settings || {}
      setSettings((prev) => ({
        ...prev,
        api_account: data.api_account?.value || '',
        private_key: data.private_key?.value || '',
        customer_code: data.customer_code?.value || '',
        customer_password: data.customer_password?.value || '',
        sandbox_uuid: data.sandbox_uuid?.value || '',
        base_url: data.base_url?.value || 'https://openapi.jtjms-sa.com',
        default_weight: data.default_weight?.value || '0.5',
        default_service_type: data.default_service_type?.value || '02',
      }))
    } catch {
      // Settings not yet configured
    } finally {
      setLoading(false)
    }
  }

  const loadWarehouses = async () => {
    try {
      const res = await axios.get('/api/warehouses')
      setWarehouses(res.data.warehouses)
    } catch {
      // No warehouses yet
    }
  }

  const saveSettings = async () => {
    setSaving(true)
    try {
      await axios.put('/api/connectors/2/settings', {
        settings: [
          { key: 'api_account', value: settings.api_account, is_encrypted: false },
          { key: 'private_key', value: settings.private_key, is_encrypted: true },
          { key: 'customer_code', value: settings.customer_code, is_encrypted: false },
          { key: 'customer_password', value: settings.customer_password, is_encrypted: true },
          { key: 'sandbox_uuid', value: settings.sandbox_uuid, is_encrypted: false },
          { key: 'base_url', value: settings.base_url, is_encrypted: false },
          { key: 'default_weight', value: settings.default_weight, is_encrypted: false },
          { key: 'default_service_type', value: settings.default_service_type, is_encrypted: false },
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
    setTesting(true)
    try {
      const res = await axios.post('/api/connectors/2/test')
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

  const saveWarehouse = async () => {
    try {
      if (editingWarehouse) {
        await axios.put(`/api/warehouses/${editingWarehouse}`, warehouseForm)
        toast.success('Warehouse updated!')
      } else {
        await axios.post('/api/warehouses', warehouseForm)
        toast.success('Warehouse created!')
      }
      setWarehouseForm({
        name: '',
        contact_name: '',
        phone: '',
        province: '',
        city: '',
        area: '',
        address: '',
        post_code: '',
        country_code: 'SA',
        is_default: false,
      })
      setEditingWarehouse(null)
      loadWarehouses()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to save warehouse')
    }
  }

  const editWarehouse = (warehouse: Warehouse) => {
    setEditingWarehouse(warehouse.id)
    setWarehouseForm({
      name: warehouse.name,
      contact_name: warehouse.contact_name,
      phone: warehouse.phone,
      province: warehouse.province,
      city: warehouse.city,
      area: warehouse.area || '',
      address: warehouse.address,
      post_code: warehouse.post_code || '',
      country_code: warehouse.country_code,
      is_default: warehouse.is_default,
    })
  }

  const deleteWarehouse = async (id: number) => {
    if (!confirm('Are you sure you want to delete this warehouse?')) return
    try {
      await axios.delete(`/api/warehouses/${id}`)
      toast.success('Warehouse deleted!')
      loadWarehouses()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to delete warehouse')
    }
  }

  if (loading) {
    return (
      <AuthenticatedLayout>
        <Head title='J&T Express Settings' />
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
      <Head title='J&T Express Settings' />

      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='space-y-6'>
          <div className='flex items-start justify-between'>
            <div>
              <h1 className='text-3xl font-bold tracking-tight'>J&T Express Settings</h1>
              <p className='text-muted-foreground'>
                Configure your J&T Express API credentials and warehouse addresses
              </p>
            </div>
            <a href='/apps/jnt-express/analytics'>
              <Button variant='outline' size='sm'>
                View Analytics & COD
              </Button>
            </a>
          </div>

          <Tabs defaultValue='credentials' className='space-y-4'>
            <TabsList>
              <TabsTrigger value='credentials'>API Credentials</TabsTrigger>
              <TabsTrigger value='warehouses'>Warehouses</TabsTrigger>
            </TabsList>

            <TabsContent value='credentials' className='space-y-4'>
              <Card>
                <CardHeader>
                  <CardTitle>API Configuration</CardTitle>
                  <CardDescription>
                    Enter your J&T Express API credentials to enable shipment creation
                  </CardDescription>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <div className='grid gap-4 md:grid-cols-2'>
                    <div className='space-y-2'>
                      <div className='flex items-center gap-1.5'>
                        <Label htmlFor='api_account'>API Account</Label>
                        <FieldInfo title='API Account (apiAccount)'>
                          <p>Your J&T Express OpenAPI account number. It identifies your integration and is sent in the <code>apiAccount</code> request header.</p>
                          <p><span className='font-medium'>Where to get it:</span> issued by your J&T account manager when OpenAPI access is approved — check the J&T onboarding email or the merchant/OpenAPI portal under API credentials.</p>
                        </FieldInfo>
                      </div>
                      <Input
                        id='api_account'
                        value={settings.api_account}
                        onChange={(e) => setSettings({ ...settings, api_account: e.target.value })}
                        placeholder='Your J&T API account number'
                      />
                    </div>
                    <div className='space-y-2'>
                      <div className='flex items-center gap-1.5'>
                        <Label htmlFor='private_key'>Private Key</Label>
                        <FieldInfo title='Private Key'>
                          <p>The secret key paired with your API Account. Every request is signed with it (the header <code>digest</code>), so it must match exactly.</p>
                          <p><span className='font-medium'>Where to get it:</span> issued together with the API Account by J&T. Stored encrypted — leave the masked value untouched to keep the saved key.</p>
                        </FieldInfo>
                      </div>
                      <Input
                        id='private_key'
                        type='password'
                        value={settings.private_key}
                        onChange={(e) => setSettings({ ...settings, private_key: e.target.value })}
                        placeholder='Your J&T private key'
                      />
                    </div>
                    <div className='space-y-2'>
                      <div className='flex items-center gap-1.5'>
                        <Label htmlFor='customer_code'>Customer Code</Label>
                        <FieldInfo title='Customer Code (customerCode)'>
                          <p>Your J&T merchant/customer code. Sent on every shipment order to identify the billing account.</p>
                          <p><span className='font-medium'>Where to get it:</span> listed in your J&T merchant agreement / contract, or in the merchant portal account details. Ask your J&T account manager if unsure.</p>
                        </FieldInfo>
                      </div>
                      <Input
                        id='customer_code'
                        value={settings.customer_code}
                        onChange={(e) => setSettings({ ...settings, customer_code: e.target.value })}
                        placeholder='Your J&T customer code'
                      />
                    </div>
                    <div className='space-y-2'>
                      <div className='flex items-center gap-1.5'>
                        <Label htmlFor='customer_password'>Customer Password</Label>
                        <FieldInfo title='Customer Password'>
                          <p>The password tied to your Customer Code. Used to build the inner order digest required when creating shipments.</p>
                          <p><span className='font-medium'>Where to get it:</span> provided by J&T together with your customer code. Stored encrypted — leave the masked value untouched to keep the saved password.</p>
                        </FieldInfo>
                      </div>
                      <Input
                        id='customer_password'
                        type='password'
                        value={settings.customer_password}
                        onChange={(e) => setSettings({ ...settings, customer_password: e.target.value })}
                        placeholder='Your J&T customer password'
                      />
                    </div>
                  </div>

                  <div className='space-y-2'>
                    <div className='flex items-center gap-1.5'>
                      <Label htmlFor='base_url'>Base URL</Label>
                      <FieldInfo title='Base URL'>
                        <p>The J&T OpenAPI gateway every request is sent to.</p>
                        <p><span className='font-medium'>Production:</span> <code>https://openapi.jtjms-sa.com</code></p>
                        <p><span className='font-medium'>Sandbox / testing:</span> use the test gateway URL J&T provided for your sandbox account.</p>
                      </FieldInfo>
                    </div>
                    <Input
                      id='base_url'
                      value={settings.base_url}
                      onChange={(e) => setSettings({ ...settings, base_url: e.target.value })}
                      placeholder='https://openapi.jtjms-sa.com'
                    />
                  </div>

                  <div className='space-y-2'>
                    <div className='flex items-center gap-1.5'>
                      <Label htmlFor='sandbox_uuid'>Sandbox UUID</Label>
                      <FieldInfo title='Sandbox UUID (optional)'>
                        <p>Only for the sandbox/testing environment. When set, it is appended as <code>?uuid=</code> to every request to route it to your test account.</p>
                        <p><span className='font-medium'>Where to get it:</span> provided by J&T when they grant sandbox access. <span className='font-medium'>Leave empty for production.</span></p>
                      </FieldInfo>
                    </div>
                    <Input
                      id='sandbox_uuid'
                      value={settings.sandbox_uuid}
                      onChange={(e) => setSettings({ ...settings, sandbox_uuid: e.target.value })}
                      placeholder='Leave empty for production'
                    />
                  </div>

                  <Separator />

                  <div className='grid gap-4 md:grid-cols-2'>
                    <div className='space-y-2'>
                      <Label htmlFor='default_weight'>Default Weight (kg)</Label>
                      <Input
                        id='default_weight'
                        type='number'
                        step='0.1'
                        value={settings.default_weight}
                        onChange={(e) => setSettings({ ...settings, default_weight: e.target.value })}
                      />
                    </div>
                    <div className='space-y-2'>
                      <Label htmlFor='default_service_type'>Default Service Type</Label>
                      <select
                        id='default_service_type'
                        className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                        value={settings.default_service_type}
                        onChange={(e) => setSettings({ ...settings, default_service_type: e.target.value })}
                      >
                        <option value='01'>Express (01)</option>
                        <option value='02'>Standard (02)</option>
                      </select>
                    </div>
                  </div>

                  <div className='flex gap-3 pt-4'>
                    <Button onClick={saveSettings} disabled={saving}>
                      {saving ? 'Saving...' : 'Save Settings'}
                    </Button>
                    <Button variant='outline' onClick={testConnection} disabled={testing}>
                      {testing ? 'Testing...' : 'Test Connection'}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value='warehouses' className='space-y-4'>
              <Card>
                <CardHeader>
                  <CardTitle>{editingWarehouse ? 'Edit Warehouse' : 'Add Warehouse'}</CardTitle>
                  <CardDescription>
                    Configure your sender/warehouse addresses for shipments
                  </CardDescription>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <div className='grid gap-4 md:grid-cols-2'>
                    <div className='space-y-2'>
                      <Label>Warehouse Name</Label>
                      <Input
                        value={warehouseForm.name}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, name: e.target.value })}
                        placeholder='e.g. Riyadh Main Warehouse'
                      />
                    </div>
                    <div className='space-y-2'>
                      <Label>Contact Name</Label>
                      <Input
                        value={warehouseForm.contact_name}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, contact_name: e.target.value })}
                        placeholder='Sender contact name'
                      />
                    </div>
                    <div className='space-y-2'>
                      <Label>Phone</Label>
                      <Input
                        value={warehouseForm.phone}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, phone: e.target.value })}
                        placeholder='05XXXXXXXX'
                      />
                    </div>
                    <div className='space-y-2'>
                      <Label>Province</Label>
                      <Input
                        value={warehouseForm.province}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, province: e.target.value })}
                        placeholder='e.g. Riyadh Region'
                      />
                    </div>
                    <div className='space-y-2'>
                      <Label>City</Label>
                      <Input
                        value={warehouseForm.city}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, city: e.target.value })}
                        placeholder='e.g. Riyadh'
                      />
                    </div>
                    <div className='space-y-2'>
                      <Label>Area / District</Label>
                      <Input
                        value={warehouseForm.area}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, area: e.target.value })}
                        placeholder='e.g. Al Olaya'
                      />
                    </div>
                    <div className='space-y-2 md:col-span-2'>
                      <Label>Street Address</Label>
                      <Input
                        value={warehouseForm.address}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, address: e.target.value })}
                        placeholder='Full street address'
                      />
                    </div>
                    <div className='space-y-2'>
                      <Label>Postal Code</Label>
                      <Input
                        value={warehouseForm.post_code}
                        onChange={(e) => setWarehouseForm({ ...warehouseForm, post_code: e.target.value })}
                        placeholder='e.g. 12211'
                      />
                    </div>
                    <div className='flex items-end space-x-2'>
                      <label className='flex items-center gap-2 text-sm'>
                        <input
                          type='checkbox'
                          checked={warehouseForm.is_default}
                          onChange={(e) => setWarehouseForm({ ...warehouseForm, is_default: e.target.checked })}
                          className='rounded border-gray-300'
                        />
                        Set as default warehouse
                      </label>
                    </div>
                  </div>

                  <div className='flex gap-3 pt-2'>
                    <Button onClick={saveWarehouse}>
                      {editingWarehouse ? 'Update Warehouse' : 'Add Warehouse'}
                    </Button>
                    {editingWarehouse && (
                      <Button
                        variant='outline'
                        onClick={() => {
                          setEditingWarehouse(null)
                          setWarehouseForm({
                            name: '',
                            contact_name: '',
                            phone: '',
                            province: '',
                            city: '',
                            area: '',
                            address: '',
                            post_code: '',
                            country_code: 'SA',
                            is_default: false,
                          })
                        }}
                      >
                        Cancel
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>

              {warehouses.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Your Warehouses</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className='space-y-3'>
                      {warehouses.map((w) => (
                        <div
                          key={w.id}
                          className='flex items-center justify-between rounded-lg border p-4'
                        >
                          <div>
                            <div className='flex items-center gap-2'>
                              <p className='font-medium'>{w.name}</p>
                              {w.is_default && <Badge variant='secondary'>Default</Badge>}
                              {!w.is_active && <Badge variant='destructive'>Inactive</Badge>}
                            </div>
                            <p className='text-sm text-muted-foreground'>
                              {w.contact_name} &middot; {w.phone}
                            </p>
                            <p className='text-sm text-muted-foreground'>
                              {w.address}, {w.area ? `${w.area}, ` : ''}{w.city}, {w.province}
                            </p>
                          </div>
                          <div className='flex gap-2'>
                            <Button size='sm' variant='outline' onClick={() => editWarehouse(w)}>
                              Edit
                            </Button>
                            <Button size='sm' variant='destructive' onClick={() => deleteWarehouse(w.id)}>
                              Delete
                            </Button>
                          </div>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}
            </TabsContent>
          </Tabs>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
