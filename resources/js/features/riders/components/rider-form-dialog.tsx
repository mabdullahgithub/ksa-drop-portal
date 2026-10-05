import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { Bike, Camera, ChevronDown, Loader2, PackageSearch, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { compressImage } from '@/lib/compress-image'
import { cn } from '@/lib/utils'
import { EMPLOYMENT_TYPES, RIDER_ROLES, VEHICLE_TYPES, type RiderRow, type WarehouseOption } from '../data/types'
import { RiderAvatar } from './rider-parts'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  rider?: RiderRow | null
  warehouses: WarehouseOption[]
  onSaved: (rider: RiderRow, created: boolean) => void
}

type FormState = Record<
  | 'name' | 'phone' | 'role' | 'name_ar' | 'national_id' | 'nationality' | 'vehicle_type' | 'vehicle_plate'
  | 'license_number' | 'license_expiry' | 'warehouse_id' | 'city' | 'employment_type' | 'iban' | 'delivery_rate' | 'attempt_rate'
  | 'emergency_contact_name' | 'emergency_contact_phone' | 'notes',
  string
>

const NONE = '__none'

function initialState(rider?: RiderRow | null): FormState {
  return {
    name: rider?.name ?? '',
    phone: rider?.phone_local ?? '',
    role: rider?.role ?? 'rider',
    name_ar: rider?.name_ar ?? '',
    national_id: rider?.national_id ?? '',
    nationality: rider?.nationality ?? '',
    vehicle_type: rider?.vehicle_type ?? '',
    vehicle_plate: rider?.vehicle_plate ?? '',
    license_number: rider?.license_number ?? '',
    license_expiry: rider?.license_expiry ?? '',
    warehouse_id: rider?.warehouse_id ? String(rider.warehouse_id) : '',
    city: rider?.city ?? '',
    employment_type: rider?.employment_type ?? '',
    iban: rider?.iban ?? '',
    delivery_rate: rider?.delivery_rate != null ? String(rider.delivery_rate) : '',
    attempt_rate: rider?.attempt_rate != null ? String(rider.attempt_rate) : '',
    emergency_contact_name: rider?.emergency_contact_name ?? '',
    emergency_contact_phone: rider?.emergency_contact_phone ?? '',
    notes: rider?.notes ?? '',
  }
}

const ROLE_ICONS = { rider: Bike, inventory_manager: PackageSearch } as const

/**
 * Add / edit a rider or an inventory manager. Only name and phone are
 * required; everything else sits under "More details".
 */
export function RiderFormDialog({ open, onOpenChange, rider, warehouses, onSaved }: Props) {
  const isEdit = !!rider
  const [form, setForm] = useState<FormState>(() => initialState(rider))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)
  const [moreOpen, setMoreOpen] = useState(false)

  // Optional photo: picked here, sent after the rider is saved.
  const [photo, setPhoto] = useState<{ blob: Blob; url: string } | null>(null)
  const [removePhoto, setRemovePhoto] = useState(false)
  const [preparingPhoto, setPreparingPhoto] = useState(false)
  const photoInput = useRef<HTMLInputElement>(null)

  useEffect(() => () => {
    if (photo) URL.revokeObjectURL(photo.url)
  }, [photo])

  const pickPhoto = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    setPreparingPhoto(true)
    try {
      const blob = await compressImage(file, 640, 0.8)
      setPhoto({ blob, url: URL.createObjectURL(blob) })
      setRemovePhoto(false)
    } catch {
      toast.error('That file could not be read as a picture.')
    } finally {
      setPreparingPhoto(false)
    }
  }

  const shownPhoto = photo?.url ?? (removePhoto ? null : rider?.photo_url ?? null)

  // Pay, vehicle and licence are a rider's; an inventory manager has none.
  const isManager = form.role === 'inventory_manager'
  const role = RIDER_ROLES.find((option) => option.value === form.role)

  const set = (key: keyof FormState) => (value: string) => setForm((f) => ({ ...f, [key]: value }))
  const input = (key: keyof FormState) => ({
    value: form[key],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => set(key)(e.target.value),
  })

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setSaving(true)
    setErrors({})

    // Empty optional fields go as null so an edit can clear them.
    const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v.trim() === '' ? null : v.trim()]))

    try {
      const { data } = isEdit
        ? await axios.put(`/api/riders/${rider!.id}`, payload)
        : await axios.post('/api/riders', payload)
      let saved: RiderRow = data.rider

      try {
        if (photo) {
          const form = new FormData()
          form.append('photo', photo.blob, 'rider.jpg')
          saved = (await axios.post(`/api/riders/${saved.id}/photo`, form)).data.rider
        } else if (removePhoto && rider?.photo_url) {
          saved = (await axios.delete(`/api/riders/${saved.id}/photo`)).data.rider
        }
      } catch (photoErr: any) {
        toast.error(photoErr.response?.data?.errors?.photo?.[0] || 'The rider was saved, but the photo could not be uploaded.')
      }

      toast.success(data.message)
      onSaved(saved, !isEdit)
      onOpenChange(false)
    } catch (err: any) {
      const fieldErrors = err.response?.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(fieldErrors).map(([k, v]) => [k, (v as string[])[0]])))
      // Surface errors hidden under the collapsed section.
      if (Object.keys(fieldErrors).some((k) => !['name', 'phone', 'role', 'delivery_rate', 'attempt_rate'].includes(k))) setMoreOpen(true)
      if (!Object.keys(fieldErrors).length) toast.error(err.response?.data?.message || 'Could not save the rider.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90dvh] overflow-y-auto sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle>{isEdit ? `Edit ${isManager ? 'inventory manager' : 'rider'}` : `Add ${isManager ? 'inventory manager' : 'rider'}`}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'Update their details.' : 'Name and phone are enough. You can add the rest any time.'}
          </DialogDescription>
        </DialogHeader>

        <form id='rider-form' onSubmit={submit} className='space-y-4'>
          <div className='flex items-center gap-4'>
            <RiderAvatar name={form.name || '?'} photoUrl={shownPhoto} className='size-16 text-lg' />
            <div className='space-y-1'>
              <div className='flex flex-wrap gap-2'>
                <input ref={photoInput} type='file' accept='image/*' className='hidden' onChange={pickPhoto} />
                <Button type='button' variant='outline' size='sm' onClick={() => photoInput.current?.click()} disabled={preparingPhoto}>
                  {preparingPhoto ? <Loader2 className='me-1 h-3.5 w-3.5 animate-spin' /> : <Camera className='me-1 h-3.5 w-3.5' />}
                  {shownPhoto ? 'Change photo' : 'Add photo'}
                </Button>
                {shownPhoto && (
                  <Button
                    type='button'
                    variant='ghost'
                    size='sm'
                    onClick={() => {
                      setPhoto(null)
                      setRemovePhoto(true)
                    }}
                  >
                    <Trash2 className='me-1 h-3.5 w-3.5' />
                    Remove
                  </Button>
                )}
              </div>
              <p className='text-xs text-muted-foreground'>Optional. They can also set it from the app.</p>
            </div>
          </div>

          <Field label='Role' error={errors.role} hint={role?.hint}>
            <div role='radiogroup' aria-label='Role' className='grid grid-cols-2 gap-1 rounded-lg bg-muted p-1'>
              {RIDER_ROLES.map((option) => {
                const Icon = ROLE_ICONS[option.value]
                const selected = form.role === option.value
                return (
                  <button
                    key={option.value}
                    type='button'
                    role='radio'
                    aria-checked={selected}
                    onClick={() => set('role')(option.value)}
                    className={cn(
                      'flex h-9 items-center justify-center gap-1.5 rounded-md text-sm font-medium transition-colors',
                      selected ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground'
                    )}
                  >
                    <Icon className='h-4 w-4' />
                    {option.label}
                  </button>
                )
              })}
            </div>
          </Field>

          <Field label='Full name' error={errors.name} required>
            <Input {...input('name')} autoFocus autoComplete='off' placeholder='e.g. Ahmed Khan' />
          </Field>
          <Field
            label='Mobile number'
            error={errors.phone}
            required
            hint='Saudi (05…) or Pakistani (03…) mobile. They sign in to the app with this number.'
          >
            <Input {...input('phone')} inputMode='tel' autoComplete='off' placeholder='05XXXXXXXX or 03XXXXXXXXX' dir='ltr' />
          </Field>

          {/* What KSA Drop pays this rider. Agreed rider by rider. */}
          {!isManager && (
            <div className='space-y-1.5'>
              <div className='grid grid-cols-2 gap-4'>
                <Field label='Pay per delivery (SAR)' error={errors.delivery_rate}>
                  <Input {...input('delivery_rate')} type='number' inputMode='decimal' min='0' step='0.01' placeholder='0.00' dir='ltr' />
                </Field>
                <Field label='Pay per attempt (SAR)' error={errors.attempt_rate}>
                  <Input {...input('attempt_rate')} type='number' inputMode='decimal' min='0' step='0.01' placeholder='0.00' dir='ltr' />
                </Field>
              </div>
              <p className='text-xs text-muted-foreground'>
                What KSA Drop pays this rider: for each delivered order, and for each visit the customer did not take the parcel on
                (the rider takes a photo of the place). Orders marked Cancelled are not paid. A new rate applies from the next order.
              </p>
            </div>
          )}

          <Collapsible open={moreOpen} onOpenChange={setMoreOpen}>
            <CollapsibleTrigger asChild>
              <button type='button' className='flex w-full items-center justify-between rounded-md border px-3 py-2 text-sm font-medium'>
                More details (optional)
                <ChevronDown className={cn('h-4 w-4 transition-transform', moreOpen && 'rotate-180')} />
              </button>
            </CollapsibleTrigger>
            <CollapsibleContent className='CollapsibleContent'>
              <div className='grid grid-cols-1 gap-4 pt-4 sm:grid-cols-2'>
                <Field label='Name in Arabic' error={errors.name_ar}>
                  <Input {...input('name_ar')} dir='rtl' />
                </Field>
                <Field label='Hub' error={errors.warehouse_id}>
                  <Select value={form.warehouse_id || NONE} onValueChange={(v) => set('warehouse_id')(v === NONE ? '' : v)}>
                    <SelectTrigger className='w-full'>
                      <SelectValue placeholder='Default hub' />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={NONE}>Default hub</SelectItem>
                      {warehouses.map((w) => (
                        <SelectItem key={w.id} value={String(w.id)}>
                          {w.name}
                          {w.is_default ? ' (default)' : ''}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>
                <Field label='City' error={errors.city}>
                  <Input {...input('city')} placeholder='e.g. Riyadh' />
                </Field>
                <Field label='Nationality' error={errors.nationality}>
                  <Input {...input('nationality')} />
                </Field>
                <Field label='Iqama / National ID' error={errors.national_id} hint='Stored encrypted.'>
                  <Input {...input('national_id')} inputMode='numeric' dir='ltr' />
                </Field>
                <Field label='Employment' error={errors.employment_type}>
                  <OptionalSelect value={form.employment_type} onChange={set('employment_type')} options={EMPLOYMENT_TYPES} />
                </Field>
                {!isManager && (
                  <>
                    <Field label='Vehicle' error={errors.vehicle_type}>
                      <OptionalSelect value={form.vehicle_type} onChange={set('vehicle_type')} options={VEHICLE_TYPES} />
                    </Field>
                    <Field label='Plate number' error={errors.vehicle_plate}>
                      <Input {...input('vehicle_plate')} />
                    </Field>
                    <Field label='Driving licence number' error={errors.license_number}>
                      <Input {...input('license_number')} dir='ltr' />
                    </Field>
                    <Field label='Licence expiry' error={errors.license_expiry}>
                      <Input {...input('license_expiry')} type='date' />
                    </Field>
                    <Field label='IBAN' error={errors.iban} hint='For freelancer payouts. Stored encrypted.' className='sm:col-span-2'>
                      <Input {...input('iban')} placeholder='SA…' dir='ltr' />
                    </Field>
                  </>
                )}
                <Field label='Emergency contact' error={errors.emergency_contact_name}>
                  <Input {...input('emergency_contact_name')} />
                </Field>
                <Field label='Emergency phone' error={errors.emergency_contact_phone}>
                  <Input {...input('emergency_contact_phone')} inputMode='tel' dir='ltr' />
                </Field>
                <Field label='Notes' error={errors.notes} className='sm:col-span-2'>
                  <Textarea {...input('notes')} rows={2} className='resize-none' />
                </Field>
              </div>
            </CollapsibleContent>
          </Collapsible>
        </form>

        <DialogFooter>
          <Button type='button' variant='outline' onClick={() => onOpenChange(false)} disabled={saving}>
            Cancel
          </Button>
          <Button type='submit' form='rider-form' disabled={saving}>
            {saving ? 'Saving…' : isEdit ? 'Save changes' : isManager ? 'Add inventory manager' : 'Add rider'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function Field({
  label,
  error,
  hint,
  required,
  className,
  children,
}: {
  label: string
  error?: string
  hint?: string
  required?: boolean
  className?: string
  children: React.ReactNode
}) {
  return (
    <div className={cn('space-y-1.5', className)}>
      <Label>
        {label}
        {required && <span className='text-destructive'>*</span>}
      </Label>
      {children}
      {error ? (
        <p className='text-xs text-destructive'>{error}</p>
      ) : hint ? (
        <p className='text-xs text-muted-foreground'>{hint}</p>
      ) : null}
    </div>
  )
}

function OptionalSelect({
  value,
  onChange,
  options,
}: {
  value: string
  onChange: (value: string) => void
  options: readonly { value: string; label: string }[]
}) {
  return (
    <Select value={value || NONE} onValueChange={(v) => onChange(v === NONE ? '' : v)}>
      <SelectTrigger className='w-full'>
        <SelectValue placeholder='—' />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={NONE}>—</SelectItem>
        {options.map((o) => (
          <SelectItem key={o.value} value={o.value}>
            {o.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
