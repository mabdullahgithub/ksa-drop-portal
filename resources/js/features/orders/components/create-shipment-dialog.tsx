import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'
import { useState, useEffect } from 'react'
import { type Order } from '@/types/order'
import { JntProvinceSelect, JntCitySelect } from '@/components/jnt-location-select'
import { LogesTechsVillageSelect } from '@/components/logestechs-village-select'

type Courier = 'jnt_express' | 'imile' | 'logestechs'

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
  is_default: boolean
}

const isBlankField = (value: string) => {
  const v = value.trim()
  return v === '' || v === '-'
}

interface CreateShipmentDialogProps {
  order: Order | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

export function CreateShipmentDialog({ order, open, onOpenChange, onSuccess }: CreateShipmentDialogProps) {
  const [warehouses, setWarehouses] = useState<Warehouse[]>([])
  const [loading, setLoading] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [selectedWarehouse, setSelectedWarehouse] = useState<number | ''>('')
  const [courier, setCourier] = useState<Courier>('jnt_express')
  const [form, setForm] = useState({
    weight: '0.5',
    length: '',
    width: '',
    height: '',
    service_type: '02',
    goods_type: 'ITN1',
    remark: '',
    receiver_name: '',
    receiver_phone: '',
    receiver_province: '',
    receiver_city: '',
    receiver_area: '',
    receiver_address: '',
    receiver_post_code: '',
    receiver_short_address: '',
    // LogesTechs only — it resolves the destination from a district rather
    // than province/city, and district names aren't unique, so the id is what
    // actually gets submitted.
    receiver_village: '',
    receiver_village_id: '',
    logestechs_service_type: 'STANDARD',
  })

  const isLogesTechs = courier === 'logestechs'

  useEffect(() => {
    if (open) {
      loadWarehouses()
      if (order) {
        setForm((prev) => ({
          ...prev,
          receiver_name: order.shipping_name || order.customer_name || '',
          receiver_phone: order.shipping_phone || order.customer_phone || '',
          receiver_province: order.shipping_province || '',
          receiver_city: order.shipping_city || '',
          receiver_area: '',
          receiver_address: order.shipping_address1 || '',
          receiver_post_code: order.shipping_zip || '',
          receiver_short_address: order.shipping_address2 || '',
        }))
      }
    }
  }, [open, order])

  const loadWarehouses = async () => {
    setLoading(true)
    try {
      const res = await axios.get('/api/warehouses')
      const wh = res.data.warehouses || []
      setWarehouses(wh)
      const defaultWh = wh.find((w: Warehouse) => w.is_default) || wh[0]
      if (defaultWh) setSelectedWarehouse(defaultWh.id)
    } catch {
      toast.error('Failed to load warehouses')
    } finally {
      setLoading(false)
    }
  }

  const missingFields: string[] = []
  if (isBlankField(form.receiver_name)) missingFields.push('Receiver Name')
  if (isBlankField(form.receiver_phone)) missingFields.push('Receiver Phone')
  if (isLogesTechs) {
    // LogesTechs resolves the destination from a district, not province/city,
    // and rejects the shipment outright without a street address or a Saudi
    // National Address ("العنوان الوطني إجباري").
    // The id is what LogesTechs resolves against — a typed name alone is
    // rejected with "Invalid Parameter 'model.cityId' null" — so the district
    // must have come from the lookup, not free text.
    if (isBlankField(form.receiver_village_id)) {
      missingFields.push('District')
    }
    if (isBlankField(form.receiver_address)) missingFields.push('Street Address')
    if (isBlankField(form.receiver_short_address)) missingFields.push('National Address')
  } else {
    if (isBlankField(form.receiver_province)) missingFields.push('Receiver Province')
    if (isBlankField(form.receiver_city)) missingFields.push('Receiver City')
    if (isBlankField(form.receiver_address)) missingFields.push('Receiver Address')
  }

  // For the other couriers a missing field is a warning — they may still
  // accept it. LogesTechs rejects outright without a district and street
  // address, so there's nothing to gain from letting the request go out.
  const blockedByMissing = isLogesTechs && missingFields.length > 0

  const handleSubmit = async () => {
    if (!selectedWarehouse) {
      toast.error('Please select a warehouse')
      return
    }
    if (!order) return

    setSubmitting(true)
    try {
      const payload: any = {
        order_id: order.id,
        warehouse_id: selectedWarehouse,
        courier,
        weight: parseFloat(form.weight) || 0.5,
        receiver_name: form.receiver_name,
        receiver_phone: form.receiver_phone,
        receiver_province: form.receiver_province,
        receiver_city: form.receiver_city,
        receiver_area: form.receiver_area,
        receiver_address: form.receiver_address,
        receiver_post_code: form.receiver_post_code,
        receiver_short_address: form.receiver_short_address || undefined,
        remark: form.remark || undefined,
      }

      if (courier === 'jnt_express') {
        payload.service_type = form.service_type
        payload.goods_type = form.goods_type
      }

      if (isLogesTechs) {
        payload.service_type = form.logestechs_service_type
        payload.receiver_village = form.receiver_village || undefined
        // The id is what disambiguates duplicate district names — send it
        // whenever the picker resolved one.
        payload.receiver_village_id = form.receiver_village_id
          ? Number(form.receiver_village_id)
          : undefined
      }

      if (form.length) payload.length = parseFloat(form.length)
      if (form.width) payload.width = parseFloat(form.width)
      if (form.height) payload.height = parseFloat(form.height)

      const res = await axios.post('/api/shipments', payload)
      toast.success(`Shipment created! Tracking: ${res.data.shipment.tracking_number}`)
      onOpenChange(false)
      onSuccess?.()
    } catch (err: any) {
      toast.error(err.response?.data?.error || err.response?.data?.message || 'Failed to create shipment')
    } finally {
      setSubmitting(false)
    }
  }

  if (!order) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-2xl max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>Create Shipment — Order {order.order_number}</DialogTitle>
        </DialogHeader>

        {loading ? (
          <div className='py-8 text-center text-muted-foreground'>Loading...</div>
        ) : (
          <div className='space-y-5'>
            {/* Warning Banner */}
            {missingFields.length > 0 && (
              <div className='flex items-start gap-2 rounded-md bg-yellow-50 dark:bg-yellow-900/20 p-3 text-sm text-yellow-800 dark:text-yellow-300'>
                <AlertTriangle className='h-4 w-4 mt-0.5 shrink-0' />
                <div>
                  <span className='font-medium'>Missing fields: </span>
                  {missingFields.join(', ')}.{' '}
                  {blockedByMissing
                    ? 'LogesTechs requires these — fill them in to continue.'
                    : 'You can still proceed — the courier will reject if invalid.'}
                </div>
              </div>
            )}

            {/* Courier Selection */}
            <div className='space-y-2'>
              <Label className='font-semibold'>Courier</Label>
              <select
                className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                value={courier}
                onChange={(e) => setCourier(e.target.value as Courier)}
              >
                <option value='jnt_express'>J&amp;T Express</option>
                <option value='imile'>iMile</option>
                <option value='logestechs'>LogesTechs</option>
              </select>
            </div>

            {/* Warehouse Selection */}
            <div className='space-y-2'>
              <Label className='font-semibold'>Sender Warehouse</Label>
              <select
                className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                value={selectedWarehouse}
                onChange={(e) => setSelectedWarehouse(Number(e.target.value))}
              >
                <option value=''>Select a warehouse...</option>
                {warehouses.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name} {w.is_default ? '(Default)' : ''} — {w.city}
                  </option>
                ))}
              </select>
              {warehouses.length === 0 && (
                <p className='text-xs text-muted-foreground'>
                  No warehouses configured. Go to J&T Settings to add one.
                </p>
              )}
            </div>

            <Separator />

            {/* Receiver Address (Editable) */}
            <div className='space-y-3'>
              <Label className='font-semibold'>Receiver Address</Label>
              <div className='grid gap-3 md:grid-cols-2'>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground'>Name</Label>
                  <Input
                    value={form.receiver_name}
                    onChange={(e) => setForm({ ...form, receiver_name: e.target.value })}
                  />
                </div>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground'>Phone</Label>
                  <Input
                    value={form.receiver_phone}
                    onChange={(e) => setForm({ ...form, receiver_phone: e.target.value })}
                  />
                </div>
                {isLogesTechs ? (
                  // LogesTechs has no province/city fields — it resolves the
                  // destination (and its own city/region) from the district id.
                  <div className='space-y-1 md:col-span-2'>
                    <Label className='text-xs text-muted-foreground'>District</Label>
                    <LogesTechsVillageSelect
                      value={form.receiver_village}
                      valueId={form.receiver_village_id}
                      onChange={({ id, name }) =>
                        setForm((prev) => ({ ...prev, receiver_village: name, receiver_village_id: id }))
                      }
                    />
                    <p className='text-xs text-muted-foreground'>
                      LogesTechs delivers by district — the city and region are derived from your selection.
                    </p>
                  </div>
                ) : (
                  <>
                    <div className='space-y-1'>
                      <Label className='text-xs text-muted-foreground'>Province / Region</Label>
                      <JntProvinceSelect
                        value={form.receiver_province}
                        onChange={(province) => setForm({ ...form, receiver_province: province, receiver_city: '' })}
                      />
                    </div>
                    <div className='space-y-1'>
                      <Label className='text-xs text-muted-foreground'>City</Label>
                      <JntCitySelect
                        province={form.receiver_province}
                        value={form.receiver_city}
                        onChange={(city) => setForm((prev) => ({ ...prev, receiver_city: city }))}
                        onProvinceChange={(province) => setForm((prev) => ({ ...prev, receiver_province: province }))}
                      />
                    </div>
                    <div className='space-y-1'>
                      <Label className='text-xs text-muted-foreground'>Area / District <span className='italic'>(optional)</span></Label>
                      <Input
                        value={form.receiver_area}
                        onChange={(e) => setForm({ ...form, receiver_area: e.target.value })}
                      />
                    </div>
                  </>
                )}
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground'>Postal Code <span className='italic'>(optional)</span></Label>
                  <Input
                    value={form.receiver_post_code}
                    onChange={(e) => setForm({ ...form, receiver_post_code: e.target.value })}
                  />
                </div>
                <div className='space-y-1 md:col-span-2'>
                  <Label className='text-xs text-muted-foreground'>
                    Short Address (Saudi National Address
                    {isLogesTechs ? '' : ' — optional'})
                    {isLogesTechs && <span className='ms-1 text-destructive'>*</span>}
                  </Label>
                  <Input
                    value={form.receiver_short_address}
                    onChange={(e) => setForm({ ...form, receiver_short_address: e.target.value })}
                    placeholder='e.g. BLDN1234'
                    maxLength={50}
                  />
                  {isLogesTechs && (
                    <p className='text-xs text-muted-foreground'>Required by LogesTechs.</p>
                  )}
                </div>
                <div className='space-y-1 md:col-span-2'>
                  <Label className='text-xs text-muted-foreground'>
                    Street Address{isLogesTechs && <span className='ms-1 text-destructive'>*</span>}
                  </Label>
                  <Input
                    value={form.receiver_address}
                    onChange={(e) => setForm({ ...form, receiver_address: e.target.value })}
                  />
                  {isLogesTechs && (
                    <p className='text-xs text-muted-foreground'>Required by LogesTechs.</p>
                  )}
                </div>
              </div>
            </div>

            <Separator />

            {/* Package Details */}
            <div className='space-y-3'>
              <Label className='font-semibold'>Package Details</Label>
              <div className='grid gap-3 md:grid-cols-4'>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground'>Weight (kg)</Label>
                  <Input
                    type='number'
                    step='0.1'
                    value={form.weight}
                    onChange={(e) => setForm({ ...form, weight: e.target.value })}
                  />
                </div>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground whitespace-nowrap'>Length (cm)</Label>
                  <Input
                    type='number'
                    value={form.length}
                    onChange={(e) => setForm({ ...form, length: e.target.value })}
                    placeholder='Optional'
                  />
                </div>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground whitespace-nowrap'>Width (cm)</Label>
                  <Input
                    type='number'
                    value={form.width}
                    onChange={(e) => setForm({ ...form, width: e.target.value })}
                    placeholder='Optional'
                  />
                </div>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground whitespace-nowrap'>Height (cm)</Label>
                  <Input
                    type='number'
                    value={form.height}
                    onChange={(e) => setForm({ ...form, height: e.target.value })}
                    placeholder='Optional'
                  />
                </div>
              </div>
              {isLogesTechs && (
                <div className='grid gap-3 md:grid-cols-2'>
                  <div className='space-y-1'>
                    <Label className='text-xs text-muted-foreground'>Service Type</Label>
                    <select
                      className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                      value={form.logestechs_service_type}
                      onChange={(e) => setForm({ ...form, logestechs_service_type: e.target.value })}
                    >
                      <option value='STANDARD'>Standard</option>
                      <option value='EXPRESS'>Express</option>
                      <option value='THREE_TO_FIVE_DAYS'>3–5 Days</option>
                      <option value='SEA'>Sea</option>
                      <option value='AIR'>Air</option>
                    </select>
                  </div>
                </div>
              )}
              {courier === 'jnt_express' && (
                <div className='grid gap-3 md:grid-cols-2'>
                  <div className='space-y-1'>
                    <Label className='text-xs text-muted-foreground'>Service Type</Label>
                    <select
                      className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                      value={form.service_type}
                      onChange={(e) => setForm({ ...form, service_type: e.target.value })}
                    >
                      <option value='01'>Express (pickup at door)</option>
                      <option value='02'>Standard (drop at J&amp;T store)</option>
                    </select>
                  </div>
                  <div className='space-y-1'>
                    <Label className='text-xs text-muted-foreground'>Goods Type</Label>
                    <select
                      className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                      value={form.goods_type}
                      onChange={(e) => setForm({ ...form, goods_type: e.target.value })}
                    >
                      <option value='ITN1'>Clothes (ITN1)</option>
                      <option value='ITN2'>Document (ITN2)</option>
                      <option value='ITN3'>Food (ITN3)</option>
                      <option value='ITN4'>Others (ITN4)</option>
                      <option value='ITN5'>Digital Product (ITN5)</option>
                      <option value='ITN6'>Daily Necessities (ITN6)</option>
                      <option value='ITN7'>Fragile Items (ITN7)</option>
                    </select>
                  </div>
                </div>
              )}
              <div className='space-y-1'>
                <Label className='text-xs text-muted-foreground'>Remark (optional, forwarded to the courier)</Label>
                <Input
                  value={form.remark}
                  onChange={(e) => setForm({ ...form, remark: e.target.value })}
                  placeholder='Order notes or special instructions'
                  maxLength={200}
                />
              </div>
            </div>
          </div>
        )}

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={submitting || !selectedWarehouse || blockedByMissing}>
            {submitting ? 'Creating...' : 'Create Shipment'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
