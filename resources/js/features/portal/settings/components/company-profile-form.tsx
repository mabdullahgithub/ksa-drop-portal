import { useRef, useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import { Camera, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'

export function CompanyProfileForm() {
  const { auth } = usePage().props as any
  const client = auth.client
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [logoPreview, setLogoPreview] = useState<string | null>(null)

  const form = useForm({
    company_name: client?.company_name || '',
    contact_person: client?.contact_person || '',
    phone: client?.phone || '',
    secondary_phone: client?.secondary_phone || '',
    address: client?.address || '',
    city: client?.city || '',
    country: client?.country || 'SA',
    postal_code: client?.postal_code || '',
    tax_id: client?.tax_id || '',
    commercial_registration: client?.commercial_registration || '',
  })

  const logoForm = useForm<{ logo: File | null }>({
    logo: null,
  })

  const removeLogoForm = useForm({})

  const initials = (client?.company_name || 'C')
    .split(' ')
    .map((n: string) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)

  function handleLogoChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return

    setLogoPreview(URL.createObjectURL(file))
    logoForm.setData('logo', file)

    logoForm.post(route('portal.settings.logo.update'), {
      forceFormData: true,
      onSuccess: () => {
        setLogoPreview(null)
        toast.success('Company logo updated.')
      },
      onError: () => setLogoPreview(null),
    })
  }

  function handleRemoveLogo() {
    removeLogoForm.delete(route('portal.settings.logo.remove'), {
      onSuccess: () => {
        setLogoPreview(null)
        toast.success('Company logo removed.')
      },
    })
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    form.post(route('portal.settings.company-profile.update'), {
      onSuccess: () => toast.success('Company profile updated successfully.'),
    })
  }

  const logoSrc = logoPreview || (client?.logo ? `/storage/${client.logo}` : undefined)

  return (
    <div className='space-y-8'>
      {/* Logo Upload */}
      <div>
        <h4 className='text-sm font-medium mb-1'>Company Logo</h4>
        <p className='text-xs text-muted-foreground mb-4'>JPG, PNG or WebP. Max 2MB.</p>
        <div className='flex items-center gap-6'>
          <div className='relative'>
            <Avatar className='h-20 w-20'>
              <AvatarImage src={logoSrc} alt={client?.company_name} />
              <AvatarFallback className='text-lg'>{initials}</AvatarFallback>
            </Avatar>
            <button
              type='button'
              onClick={() => fileInputRef.current?.click()}
              className='bg-primary text-primary-foreground absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full shadow-sm transition-opacity hover:opacity-90'
            >
              <Camera size={14} />
            </button>
            <input
              ref={fileInputRef}
              type='file'
              accept='image/jpeg,image/png,image/webp'
              className='hidden'
              onChange={handleLogoChange}
            />
          </div>
          <div className='space-y-2'>
            <div className='flex gap-2'>
              <Button
                type='button'
                variant='outline'
                size='sm'
                onClick={() => fileInputRef.current?.click()}
                disabled={logoForm.processing}
              >
                Upload
              </Button>
              {client?.logo && (
                <Button
                  type='button'
                  variant='outline'
                  size='sm'
                  onClick={handleRemoveLogo}
                  disabled={removeLogoForm.processing}
                >
                  <Trash2 size={14} className='mr-1' />
                  Remove
                </Button>
              )}
            </div>
            {logoForm.errors.logo && (
              <p className='text-destructive text-xs'>{logoForm.errors.logo}</p>
            )}
          </div>
        </div>
      </div>

      <Separator />

      {/* Company Info Form */}
      <div>
        <h4 className='text-sm font-medium mb-1'>Company Information</h4>
        <p className='text-xs text-muted-foreground mb-4'>Update your business details.</p>

        <form onSubmit={handleSubmit} className='space-y-4'>
          <div className='grid grid-cols-2 gap-4'>
            <div className='space-y-2'>
              <Label htmlFor='cp-company'>Company Name</Label>
              <Input id='cp-company' value={form.data.company_name} onChange={(e) => form.setData('company_name', e.target.value)} />
              {form.errors.company_name && <p className='text-destructive text-xs'>{form.errors.company_name}</p>}
            </div>
            <div className='space-y-2'>
              <Label htmlFor='cp-contact'>Contact Person</Label>
              <Input id='cp-contact' value={form.data.contact_person} onChange={(e) => form.setData('contact_person', e.target.value)} />
            </div>
          </div>

          <div className='grid grid-cols-2 gap-4'>
            <div className='space-y-2'>
              <Label htmlFor='cp-phone'>Phone</Label>
              <Input id='cp-phone' value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='cp-phone2'>Secondary Phone</Label>
              <Input id='cp-phone2' value={form.data.secondary_phone} onChange={(e) => form.setData('secondary_phone', e.target.value)} />
            </div>
          </div>

          <div className='space-y-2'>
            <Label htmlFor='cp-address'>Address</Label>
            <Input id='cp-address' value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
          </div>

          <div className='grid grid-cols-3 gap-4'>
            <div className='space-y-2'>
              <Label htmlFor='cp-city'>City</Label>
              <Input id='cp-city' value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='cp-country'>Country</Label>
              <Input id='cp-country' value={form.data.country} onChange={(e) => form.setData('country', e.target.value)} maxLength={2} />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='cp-postal'>Postal Code</Label>
              <Input id='cp-postal' value={form.data.postal_code} onChange={(e) => form.setData('postal_code', e.target.value)} />
            </div>
          </div>

          <div className='grid grid-cols-2 gap-4'>
            <div className='space-y-2'>
              <Label htmlFor='cp-tax'>Tax ID (VAT)</Label>
              <Input id='cp-tax' value={form.data.tax_id} onChange={(e) => form.setData('tax_id', e.target.value)} />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='cp-cr'>Commercial Registration</Label>
              <Input id='cp-cr' value={form.data.commercial_registration} onChange={(e) => form.setData('commercial_registration', e.target.value)} />
            </div>
          </div>

          <Button type='submit' disabled={form.processing}>
            {form.processing ? 'Saving...' : 'Save Changes'}
          </Button>
        </form>
      </div>
    </div>
  )
}
