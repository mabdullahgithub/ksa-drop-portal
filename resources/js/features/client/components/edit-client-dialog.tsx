import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Switch } from '@/components/ui/switch'
import { Separator } from '@/components/ui/separator'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useClientMutations } from '@/hooks/useClients'
import type { Client } from '@/types/client'

interface EditClientDialogProps {
  client: Client | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess: () => void
}

export function EditClientDialog({ client, open, onOpenChange, onSuccess }: EditClientDialogProps) {
  const { updateClient, loading } = useClientMutations()

  const [clientTypes, setClientTypes] = useState<string[]>([])
  const [companyName, setCompanyName] = useState('')
  const [clientId, setClientId] = useState('')
  const [contactPerson, setContactPerson] = useState('')
  const [phone, setPhone] = useState('')
  const [secondaryPhone, setSecondaryPhone] = useState('')
  const [address, setAddress] = useState('')
  const [city, setCity] = useState('')
  const [country, setCountry] = useState('SA')
  const [postalCode, setPostalCode] = useState('')
  const [taxId, setTaxId] = useState('')
  const [commercialRegistration, setCommercialRegistration] = useState('')
  const [portalFeatures, setPortalFeatures] = useState<string[]>([])
  const [charges, setCharges] = useState<Record<string, string>>({
    delivery: '',
    return: '',
    cod: '',
    warehousing: '',
    call_confirmation: '',
    vat: '',
    other: '',
  })
  const [notes, setNotes] = useState('')

  useEffect(() => {
    if (client) {
      setClientTypes(client.client_types || [])
      setCompanyName(client.company_name || '')
      setClientId(client.client_id || '')
      setContactPerson(client.contact_person || '')
      setPhone(client.phone || '')
      setSecondaryPhone(client.secondary_phone || '')
      setAddress(client.address || '')
      setCity(client.city || '')
      setCountry(client.country || 'SA')
      setPostalCode(client.postal_code || '')
      setTaxId(client.tax_id || '')
      setCommercialRegistration(client.commercial_registration || '')
      setPortalFeatures(client.portal_features || [])
      const c = client.charges || {}
      setCharges({
        delivery: c.delivery != null ? String(c.delivery) : '',
        return: c.return != null ? String(c.return) : '',
        cod: c.cod != null ? String(c.cod) : '',
        warehousing: c.warehousing != null ? String(c.warehousing) : '',
        call_confirmation: c.call_confirmation != null ? String(c.call_confirmation) : '',
        vat: c.vat != null ? String(c.vat) : '',
        other: c.other != null ? String(c.other) : '',
      })
      setNotes(client.notes || '')
    }
  }, [client])

  const handleTypeToggle = (type: string) => {
    setClientTypes((prev) =>
      prev.includes(type) ? prev.filter((t) => t !== type) : [...prev, type]
    )
  }

  const handleFeatureToggle = (feature: string) => {
    setPortalFeatures((prev) =>
      prev.includes(feature) ? prev.filter((f) => f !== feature) : [...prev, feature]
    )
  }

  const handleChargeChange = (key: string, value: string) => {
    if (value === '' || /^\d*\.?\d*$/.test(value)) {
      setCharges((prev) => ({ ...prev, [key]: value }))
    }
  }

  const buildChargesPayload = () => {
    const result: Record<string, number> = {}
    Object.entries(charges).forEach(([key, value]) => {
      if (value !== '' && value !== null) {
        const num = parseFloat(value)
        if (!isNaN(num) && num >= 0) {
          result[key] = num
        }
      }
    })
    return Object.keys(result).length > 0 ? result : null
  }

  const handleSubmit = async () => {
    if (!client) return
    if (clientTypes.length === 0 || !companyName) {
      toast.error('Please fill in all required fields')
      return
    }

    const vatValue = charges.vat ? parseFloat(charges.vat) : null
    if (vatValue !== null && (vatValue < 0 || vatValue > 100)) {
      toast.error('VAT percentage must be between 0 and 100')
      return
    }

    const payload: Record<string, unknown> = {
      client_types: clientTypes,
      company_name: companyName,
      client_id: clientId,
      contact_person: contactPerson || null,
      phone: phone || null,
      secondary_phone: secondaryPhone || null,
      address: address || null,
      city: city || null,
      country: country || 'SA',
      postal_code: postalCode || null,
      tax_id: taxId || null,
      commercial_registration: commercialRegistration || null,
      portal_features: portalFeatures,
      charges: buildChargesPayload(),
      notes: notes || null,
    }

    const result = await updateClient(client.id, payload)
    if (result === true) {
      toast.success('Client updated successfully')
      onOpenChange(false)
      onSuccess()
    } else if (result && typeof result === 'object' && 'errors' in result) {
      const firstError = Object.values(result.errors).flat()[0]
      toast.error(firstError || 'Validation failed')
    } else {
      toast.error('Failed to update client')
    }
  }

  if (!client) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-2xl max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>Edit Client — {client.company_name}</DialogTitle>
        </DialogHeader>

        <div className='space-y-6 py-2'>
          {/* Client Type */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Client Type *</h4>
            <div className='flex gap-4'>
              <label className='flex items-center gap-2 cursor-pointer'>
                <Checkbox checked={clientTypes.includes('dropshipper')} onCheckedChange={() => handleTypeToggle('dropshipper')} />
                <span className='text-sm'>Dropshipper</span>
              </label>
              <label className='flex items-center gap-2 cursor-pointer'>
                <Checkbox checked={clientTypes.includes('fulfilment')} onCheckedChange={() => handleTypeToggle('fulfilment')} />
                <span className='text-sm'>Fulfilment</span>
              </label>
            </div>
          </div>

          <Separator />

          {/* Portal Access */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Portal Access</h4>
            <div className='flex flex-col gap-3'>
              <div className='flex items-center justify-between'>
                <Label htmlFor='ef-orders'>Orders</Label>
                <Switch id='ef-orders' checked={portalFeatures.includes('orders')} onCheckedChange={() => handleFeatureToggle('orders')} />
              </div>
              <div className='flex items-center justify-between'>
                <Label htmlFor='ef-inventory'>Inventory</Label>
                <Switch id='ef-inventory' checked={portalFeatures.includes('inventory')} onCheckedChange={() => handleFeatureToggle('inventory')} />
              </div>
              <div className='flex items-center justify-between'>
                <Label htmlFor='ef-revenue'>Revenue</Label>
                <Switch id='ef-revenue' checked={portalFeatures.includes('revenue')} onCheckedChange={() => handleFeatureToggle('revenue')} />
              </div>
              <div className='flex items-center justify-between'>
                <Label htmlFor='ef-finance'>Finance</Label>
                <Switch id='ef-finance' checked={portalFeatures.includes('finance')} onCheckedChange={() => handleFeatureToggle('finance')} />
              </div>
            </div>
          </div>

          <Separator />

          {/* Business Info */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Business Information</h4>
            <div className='grid gap-3'>
              <div className='grid grid-cols-2 gap-3'>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-company'>Company Name *</Label>
                  <Input id='ec-company' value={companyName} onChange={(e) => setCompanyName(e.target.value)} />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-clientid'>Client ID</Label>
                  <Input id='ec-clientid' value={clientId} onChange={(e) => setClientId(e.target.value.toUpperCase())} maxLength={6} className='font-mono uppercase' />
                </div>
              </div>
              <div className='grid grid-cols-2 gap-3'>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-contact'>Contact Person</Label>
                  <Input id='ec-contact' value={contactPerson} onChange={(e) => setContactPerson(e.target.value)} />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-phone'>Phone</Label>
                  <Input id='ec-phone' value={phone} onChange={(e) => setPhone(e.target.value)} />
                </div>
              </div>
              <div className='grid grid-cols-2 gap-3'>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-phone2'>Secondary Phone</Label>
                  <Input id='ec-phone2' value={secondaryPhone} onChange={(e) => setSecondaryPhone(e.target.value)} />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-city'>City</Label>
                  <Input id='ec-city' value={city} onChange={(e) => setCity(e.target.value)} />
                </div>
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-address'>Address</Label>
                <Input id='ec-address' value={address} onChange={(e) => setAddress(e.target.value)} />
              </div>
              <div className='grid grid-cols-3 gap-3'>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-country'>Country</Label>
                  <Input id='ec-country' value={country} onChange={(e) => setCountry(e.target.value)} maxLength={2} />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-postal'>Postal Code</Label>
                  <Input id='ec-postal' value={postalCode} onChange={(e) => setPostalCode(e.target.value)} />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='ec-tax'>Tax ID (VAT)</Label>
                  <Input id='ec-tax' value={taxId} onChange={(e) => setTaxId(e.target.value)} />
                </div>
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-cr'>Commercial Registration</Label>
                <Input id='ec-cr' value={commercialRegistration} onChange={(e) => setCommercialRegistration(e.target.value)} />
              </div>
            </div>
          </div>

          <Separator />

          {/* Charges */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Client Charges</h4>
            <p className='text-xs text-muted-foreground mb-3'>
              Set individual charges for this client. Leave blank if not applicable.
            </p>
            <div className='grid grid-cols-2 gap-3'>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-charge-delivery'>Delivery Charges (SAR)</Label>
                <Input id='ec-charge-delivery' type='text' inputMode='decimal' value={charges.delivery} onChange={(e) => handleChargeChange('delivery', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-charge-return'>Return Charges (SAR)</Label>
                <Input id='ec-charge-return' type='text' inputMode='decimal' value={charges.return} onChange={(e) => handleChargeChange('return', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-charge-cod'>COD Charges (SAR)</Label>
                <Input id='ec-charge-cod' type='text' inputMode='decimal' value={charges.cod} onChange={(e) => handleChargeChange('cod', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-charge-warehousing'>Warehousing Charges (SAR)</Label>
                <Input id='ec-charge-warehousing' type='text' inputMode='decimal' value={charges.warehousing} onChange={(e) => handleChargeChange('warehousing', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-charge-call'>Call Confirmation Charges (SAR)</Label>
                <Input id='ec-charge-call' type='text' inputMode='decimal' value={charges.call_confirmation} onChange={(e) => handleChargeChange('call_confirmation', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='ec-charge-vat'>VAT (%)</Label>
                <Input id='ec-charge-vat' type='text' inputMode='decimal' value={charges.vat} onChange={(e) => handleChargeChange('vat', e.target.value)} placeholder='15' />
              </div>
              <div className='grid gap-1.5 col-span-2'>
                <Label htmlFor='ec-charge-other'>Other Charges (SAR)</Label>
                <Input id='ec-charge-other' type='text' inputMode='decimal' value={charges.other} onChange={(e) => handleChargeChange('other', e.target.value)} placeholder='0.00' />
              </div>
            </div>
          </div>

          <Separator />

          {/* Notes */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Notes</h4>
            <div className='grid gap-1.5'>
              <Label htmlFor='ec-notes'>General Notes</Label>
              <Input id='ec-notes' value={notes} onChange={(e) => setNotes(e.target.value)} />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading}>
            {loading ? 'Saving...' : 'Save Changes'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
