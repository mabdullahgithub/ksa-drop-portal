import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import { Check, ChevronsUpDown } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Separator } from '@/components/ui/separator'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useClientMutations } from '@/hooks/useClients'

const COUNTRIES = [
  { value: 'SA', label: 'Saudi Arabia' },
  { value: 'AE', label: 'United Arab Emirates' },
  { value: 'BH', label: 'Bahrain' },
  { value: 'KW', label: 'Kuwait' },
  { value: 'OM', label: 'Oman' },
  { value: 'QA', label: 'Qatar' },
  { value: 'EG', label: 'Egypt' },
  { value: 'JO', label: 'Jordan' },
  { value: 'LB', label: 'Lebanon' },
  { value: 'IQ', label: 'Iraq' },
  { value: 'YE', label: 'Yemen' },
  { value: 'SY', label: 'Syria' },
  { value: 'PS', label: 'Palestine' },
  { value: 'SD', label: 'Sudan' },
  { value: 'LY', label: 'Libya' },
  { value: 'TN', label: 'Tunisia' },
  { value: 'DZ', label: 'Algeria' },
  { value: 'MA', label: 'Morocco' },
  { value: 'TR', label: 'Turkey' },
  { value: 'PK', label: 'Pakistan' },
  { value: 'IN', label: 'India' },
  { value: 'BD', label: 'Bangladesh' },
  { value: 'CN', label: 'China' },
  { value: 'JP', label: 'Japan' },
  { value: 'KR', label: 'South Korea' },
  { value: 'MY', label: 'Malaysia' },
  { value: 'ID', label: 'Indonesia' },
  { value: 'SG', label: 'Singapore' },
  { value: 'TH', label: 'Thailand' },
  { value: 'PH', label: 'Philippines' },
  { value: 'US', label: 'United States' },
  { value: 'CA', label: 'Canada' },
  { value: 'GB', label: 'United Kingdom' },
  { value: 'DE', label: 'Germany' },
  { value: 'FR', label: 'France' },
  { value: 'IT', label: 'Italy' },
  { value: 'ES', label: 'Spain' },
  { value: 'NL', label: 'Netherlands' },
  { value: 'SE', label: 'Sweden' },
  { value: 'NO', label: 'Norway' },
  { value: 'DK', label: 'Denmark' },
  { value: 'FI', label: 'Finland' },
  { value: 'CH', label: 'Switzerland' },
  { value: 'AT', label: 'Austria' },
  { value: 'BE', label: 'Belgium' },
  { value: 'PT', label: 'Portugal' },
  { value: 'PL', label: 'Poland' },
  { value: 'IE', label: 'Ireland' },
  { value: 'AU', label: 'Australia' },
  { value: 'NZ', label: 'New Zealand' },
  { value: 'ZA', label: 'South Africa' },
  { value: 'NG', label: 'Nigeria' },
  { value: 'KE', label: 'Kenya' },
  { value: 'BR', label: 'Brazil' },
  { value: 'MX', label: 'Mexico' },
  { value: 'AR', label: 'Argentina' },
  { value: 'CL', label: 'Chile' },
  { value: 'CO', label: 'Colombia' },
  { value: 'RU', label: 'Russia' },
  { value: 'UA', label: 'Ukraine' },
]

interface CreateClientDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess: () => void
}

function generateClientId(companyName: string): string {
  const words = companyName.trim().split(/\s+/).filter(Boolean)
  const wordCount = words.length

  let clientId = ''

  if (wordCount === 1) {
    const clean = words[0].toUpperCase().replace(/[^A-Z]/g, '').padEnd(4, 'X')
    clientId = clean.slice(0, 4)
  } else if (wordCount === 2) {
    const w1 = words[0].toUpperCase().replace(/[^A-Z]/g, '').padEnd(3, 'X')
    const w2 = words[1].toUpperCase().replace(/[^A-Z]/g, '').padEnd(3, 'X')
    clientId = w1.slice(0, 2) + w2.slice(0, 2)
  } else {
    const initials = words.map((w) => w[0].toUpperCase()).join('').replace(/[^A-Z]/g, '').padEnd(5, 'X')
    clientId = initials.slice(0, 4)
  }

  return clientId
}

export function CreateClientDialog({ open, onOpenChange, onSuccess }: CreateClientDialogProps) {
  const { createClient, loading } = useClientMutations()

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [clientType, setClientType] = useState<string>('')
  const [companyName, setCompanyName] = useState('')
  const [clientId, setClientId] = useState('')
  const [clientIdManual, setClientIdManual] = useState(false)
  const [contactPerson, setContactPerson] = useState('')
  const [phone, setPhone] = useState('')
  const [secondaryPhone, setSecondaryPhone] = useState('')
  const [address, setAddress] = useState('')
  const [city, setCity] = useState('')
  const [country, setCountry] = useState('SA')
  const [countryOpen, setCountryOpen] = useState(false)
  const [portalFeatures, setPortalFeatures] = useState<string[]>(['orders', 'revenue', 'finance'])
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
    if (!clientIdManual && companyName) {
      setClientId(generateClientId(companyName))
    }
  }, [companyName, clientIdManual])

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
    if (!name || !email || !clientType || !companyName) {
      toast.error('Please fill in all required fields')
      return
    }

    const vatValue = charges.vat ? parseFloat(charges.vat) : null
    if (vatValue !== null && (vatValue < 0 || vatValue > 100)) {
      toast.error('VAT percentage must be between 0 and 100')
      return
    }

    const payload: Record<string, unknown> = {
      name,
      email,
      client_types: [clientType],
      company_name: companyName,
      portal_features: portalFeatures,
    }

    if (clientId) payload.client_id = clientId
    if (contactPerson) payload.contact_person = contactPerson
    if (phone) payload.phone = phone
    if (secondaryPhone) payload.secondary_phone = secondaryPhone
    if (address) payload.address = address
    if (city) payload.city = city
    if (country) payload.country = country
    const chargesPayload = buildChargesPayload()
    if (chargesPayload) payload.charges = chargesPayload
    if (notes) payload.notes = notes

    const result = await createClient(payload)
    if (result === true) {
      toast.success('Client created successfully. Welcome email sent.')
      onOpenChange(false)
      onSuccess()
      resetForm()
    } else if (result && typeof result === 'object' && 'errors' in result) {
      const firstError = Object.values(result.errors).flat()[0]
      toast.error(firstError || 'Validation failed')
    } else {
      toast.error('Failed to create client')
    }
  }

  const resetForm = () => {
    setName('')
    setEmail('')
    setClientType('')
    setCompanyName('')
    setClientId('')
    setClientIdManual(false)
    setContactPerson('')
    setPhone('')
    setSecondaryPhone('')
    setAddress('')
    setCity('')
    setCountry('SA')
    setCountryOpen(false)
    setPortalFeatures(['orders', 'revenue', 'finance'])
    setCharges({ delivery: '', return: '', cod: '', warehousing: '', call_confirmation: '', vat: '', other: '' })
    setNotes('')
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-2xl max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>Create New Client</DialogTitle>
        </DialogHeader>

        <div className='space-y-6 py-2'>
          {/* Portal Credentials */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Portal Credentials</h4>
            <p className='text-xs text-muted-foreground mb-3'>
              A password will be auto-generated and emailed to the client.
            </p>
            <div className='grid grid-cols-2 gap-3'>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-name'>Name *</Label>
                <Input id='cc-name' value={name} onChange={(e) => setName(e.target.value)} placeholder='John Doe' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-email'>Email *</Label>
                <Input id='cc-email' type='email' value={email} onChange={(e) => setEmail(e.target.value)} placeholder='client@company.com' />
              </div>
            </div>
          </div>

          <Separator />

          {/* Client Type */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Client Type *</h4>
            <div className='flex gap-4'>
              <label className='flex items-center gap-2 cursor-pointer'>
                <input
                  type='radio'
                  name='client_type'
                  value='dropshipper'
                  checked={clientType === 'dropshipper'}
                  onChange={(e) => {
                    setClientType(e.target.value)
                    setPortalFeatures((pf) => pf.filter((f) => f !== 'inventory'))
                  }}
                  className='h-4 w-4'
                />
                <span className='text-sm'>Dropshipper</span>
              </label>
              <label className='flex items-center gap-2 cursor-pointer'>
                <input
                  type='radio'
                  name='client_type'
                  value='fulfilment'
                  checked={clientType === 'fulfilment'}
                  onChange={(e) => {
                    setClientType(e.target.value)
                    setPortalFeatures((pf) => pf.filter((f) => f !== 'products'))
                  }}
                  className='h-4 w-4'
                />
                <span className='text-sm'>Fulfilment</span>
              </label>
            </div>
          </div>

          <Separator />

          {/* Portal Access */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Portal Access</h4>
            <p className='text-xs text-muted-foreground mb-3'>
              Control which sections the client can see in their portal.
            </p>
            <div className='flex flex-col gap-3'>
              <div className='flex items-center justify-between'>
                <Label htmlFor='pf-orders'>Orders</Label>
                <Switch id='pf-orders' checked={portalFeatures.includes('orders')} onCheckedChange={() => handleFeatureToggle('orders')} />
              </div>
              {clientType === 'fulfilment' && (
                <div className='flex items-center justify-between'>
                  <div>
                    <Label htmlFor='pf-inventory'>Inventory</Label>
                    <p className='text-xs text-muted-foreground'>Show client's own stock & products</p>
                  </div>
                  <Switch id='pf-inventory' checked={portalFeatures.includes('inventory')} onCheckedChange={() => handleFeatureToggle('inventory')} />
                </div>
              )}
              {clientType === 'dropshipper' && (
                <div className='flex items-center justify-between'>
                  <div>
                    <Label htmlFor='pf-products'>Products Catalog</Label>
                    <p className='text-xs text-muted-foreground'>Show all published products to this dropshipper</p>
                  </div>
                  <Switch id='pf-products' checked={portalFeatures.includes('products')} onCheckedChange={() => handleFeatureToggle('products')} />
                </div>
              )}
              <div className='flex items-center justify-between'>
                <Label htmlFor='pf-revenue'>Revenue</Label>
                <Switch id='pf-revenue' checked={portalFeatures.includes('revenue')} onCheckedChange={() => handleFeatureToggle('revenue')} />
              </div>
              <div className='flex items-center justify-between'>
                <Label htmlFor='pf-finance'>Finance</Label>
                <Switch id='pf-finance' checked={portalFeatures.includes('finance')} onCheckedChange={() => handleFeatureToggle('finance')} />
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
                  <Label htmlFor='cc-company'>Company Name *</Label>
                  <Input id='cc-company' value={companyName} onChange={(e) => setCompanyName(e.target.value)} placeholder='Al Rashid Trading' />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='cc-clientid'>Client ID</Label>
                  <Input
                    id='cc-clientid'
                    value={clientId}
                    onChange={(e) => { setClientId(e.target.value.toUpperCase()); setClientIdManual(true) }}
                    placeholder='ART'
                    maxLength={6}
                    className='font-mono uppercase'
                  />
                </div>
              </div>
              <div className='grid grid-cols-2 gap-3'>
                <div className='grid gap-1.5'>
                  <Label htmlFor='cc-contact'>Contact Person</Label>
                  <Input id='cc-contact' value={contactPerson} onChange={(e) => setContactPerson(e.target.value)} />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='cc-phone'>Phone</Label>
                  <Input id='cc-phone' value={phone} onChange={(e) => setPhone(e.target.value)} />
                </div>
              </div>
              <div className='grid grid-cols-2 gap-3'>
                <div className='grid gap-1.5'>
                  <Label htmlFor='cc-phone2'>Secondary Phone</Label>
                  <Input id='cc-phone2' value={secondaryPhone} onChange={(e) => setSecondaryPhone(e.target.value)} />
                </div>
                <div className='grid gap-1.5'>
                  <Label htmlFor='cc-city'>City</Label>
                  <Input id='cc-city' value={city} onChange={(e) => setCity(e.target.value)} />
                </div>
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-address'>Address</Label>
                <Input id='cc-address' value={address} onChange={(e) => setAddress(e.target.value)} />
              </div>
              <div className='grid gap-1.5'>
                <Label>Country</Label>
                <Popover open={countryOpen} onOpenChange={setCountryOpen}>
                  <PopoverTrigger asChild>
                    <Button variant='outline' role='combobox' aria-expanded={countryOpen} className='w-full justify-between h-9 font-normal'>
                      {country ? COUNTRIES.find((c) => c.value === country)?.label ?? country : 'Select country...'}
                      <ChevronsUpDown className='ml-2 h-4 w-4 shrink-0 opacity-50' />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className='w-[--radix-popover-trigger-width] p-0' align='start'>
                    <Command>
                      <CommandInput placeholder='Search country...' />
                      <CommandList className='max-h-[200px] overflow-y-auto'>
                        <CommandEmpty>No country found.</CommandEmpty>
                        <CommandGroup>
                          {COUNTRIES.map((c) => (
                            <CommandItem key={c.value} value={c.label} onSelect={() => { setCountry(c.value); setCountryOpen(false) }}>
                              <Check className={cn('mr-2 h-4 w-4', country === c.value ? 'opacity-100' : 'opacity-0')} />
                              {c.label}
                            </CommandItem>
                          ))}
                        </CommandGroup>
                      </CommandList>
                    </Command>
                  </PopoverContent>
                </Popover>
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
                <Label htmlFor='cc-charge-delivery'>Delivery Charges (SAR)</Label>
                <Input id='cc-charge-delivery' type='text' inputMode='decimal' value={charges.delivery} onChange={(e) => handleChargeChange('delivery', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-charge-return'>Return Charges (SAR)</Label>
                <Input id='cc-charge-return' type='text' inputMode='decimal' value={charges.return} onChange={(e) => handleChargeChange('return', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-charge-cod'>COD Charges (SAR)</Label>
                <Input id='cc-charge-cod' type='text' inputMode='decimal' value={charges.cod} onChange={(e) => handleChargeChange('cod', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-charge-warehousing'>Warehousing Charges (SAR)</Label>
                <Input id='cc-charge-warehousing' type='text' inputMode='decimal' value={charges.warehousing} onChange={(e) => handleChargeChange('warehousing', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-charge-call'>Call Confirmation Charges (SAR)</Label>
                <Input id='cc-charge-call' type='text' inputMode='decimal' value={charges.call_confirmation} onChange={(e) => handleChargeChange('call_confirmation', e.target.value)} placeholder='0.00' />
              </div>
              <div className='grid gap-1.5'>
                <Label htmlFor='cc-charge-vat'>VAT (%)</Label>
                <Input id='cc-charge-vat' type='text' inputMode='decimal' value={charges.vat} onChange={(e) => handleChargeChange('vat', e.target.value)} placeholder='15' />
              </div>
              <div className='grid gap-1.5 col-span-2'>
                <Label htmlFor='cc-charge-other'>Other Charges (SAR)</Label>
                <Input id='cc-charge-other' type='text' inputMode='decimal' value={charges.other} onChange={(e) => handleChargeChange('other', e.target.value)} placeholder='0.00' />
              </div>
            </div>
          </div>

          <Separator />

          {/* Notes */}
          <div>
            <h4 className='text-sm font-semibold mb-3'>Notes</h4>
            <div className='grid gap-1.5'>
              <Label htmlFor='cc-notes'>General Notes</Label>
              <Input id='cc-notes' value={notes} onChange={(e) => setNotes(e.target.value)} />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading}>
            {loading ? 'Creating...' : 'Create Client'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
