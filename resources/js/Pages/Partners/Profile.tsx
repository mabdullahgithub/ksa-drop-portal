import { useForm } from '@inertiajs/react'
import { Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PartnerShell } from '@/features/partners/partner-shell'
import {
  AUDIENCE_SIZES, PARTNER_TYPE_LABEL, SOCIAL_KEYS, SOCIAL_LABEL, formatDate, type PartnerSummary, type PayoutMethod,
} from '@/features/partners/types'

type Props = {
  partner: PartnerSummary & {
    company_name: string | null
    phone: string
    whatsapp: string | null
    country: string
    city: string | null
    website: string | null
    social_links: Record<string, string>
    audience_size: string | null
    payout_method: PayoutMethod
    has_id_document: boolean
    terms_version: string | null
    terms_accepted_at: string | null
  }
}

export default function Profile({ partner }: Props) {
  const profile = useForm({
    display_name: partner.display_name,
    company_name: partner.company_name ?? '',
    phone: partner.phone,
    whatsapp: partner.whatsapp ?? '',
    city: partner.city ?? '',
    website: partner.website ?? '',
    social_links: Object.fromEntries(SOCIAL_KEYS.map((k) => [k, partner.social_links?.[k] ?? ''])) as Record<string, string>,
    audience_size: partner.audience_size ?? '',
    logo: null as File | null,
  })

  const payout = useForm({
    type: partner.payout_method?.type ?? 'bank',
    bank_name: partner.payout_method?.bank_name ?? '',
    holder_name: partner.payout_method?.holder_name ?? '',
    iban: partner.payout_method?.iban ?? '',
    details: partner.payout_method?.details ?? '',
  })

  const pErr = profile.errors as Record<string, string | undefined>
  const yErr = payout.errors as Record<string, string | undefined>

  return (
    <PartnerShell title='Partner profile' description='What sellers see on your referral page, and how we pay you.' partner={partner}>
      <div className='grid gap-6 lg:grid-cols-2'>
        <form onSubmit={(e) => { e.preventDefault(); profile.post('/partners/profile', { forceFormData: true, preserveScroll: true }) }}>
          <Card>
            <CardHeader>
              <CardTitle>Profile</CardTitle>
              <CardDescription>
                {PARTNER_TYPE_LABEL[partner.type]} · code <span className='font-mono font-semibold'>{partner.code}</span>. You can customize your code once from the referral tile; after that, contact our team.
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='flex items-center gap-4'>
                {partner.logo_url ? (
                  <img src={partner.logo_url} alt='' className='h-14 w-14 rounded-full object-cover ring-1 ring-border' />
                ) : (
                  <span className='flex h-14 w-14 items-center justify-center rounded-full bg-muted text-lg font-semibold'>{partner.display_name.slice(0, 1)}</span>
                )}
                <label className='flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground'>
                  <Upload className='h-4 w-4' />
                  <span>{profile.data.logo?.name ?? 'Change logo or photo'}</span>
                  <input type='file' accept='image/*' className='hidden' onChange={(e) => profile.setData('logo', e.target.files?.[0] ?? null)} />
                </label>
              </div>
              {pErr.logo && <p className='text-xs text-destructive'>{pErr.logo}</p>}

              <div className='grid gap-4 sm:grid-cols-2'>
                <F label='Display name *' error={pErr.display_name}><Input value={profile.data.display_name} onChange={(e) => profile.setData('display_name', e.target.value)} /></F>
                <F label='Company / brand' error={pErr.company_name}><Input value={profile.data.company_name} onChange={(e) => profile.setData('company_name', e.target.value)} /></F>
                <F label='Phone *' error={pErr.phone}><Input value={profile.data.phone} onChange={(e) => profile.setData('phone', e.target.value)} /></F>
                <F label='WhatsApp' error={pErr.whatsapp}><Input value={profile.data.whatsapp} onChange={(e) => profile.setData('whatsapp', e.target.value)} /></F>
                <F label='City' error={pErr.city}><Input value={profile.data.city} onChange={(e) => profile.setData('city', e.target.value)} /></F>
                <F label='Website' error={pErr.website}><Input placeholder='https://' value={profile.data.website} onChange={(e) => profile.setData('website', e.target.value)} /></F>
                <F label='Audience size' error={pErr.audience_size}>
                  <select className='h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm' value={profile.data.audience_size} onChange={(e) => profile.setData('audience_size', e.target.value)}>
                    <option value=''>Select…</option>
                    {AUDIENCE_SIZES.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                  </select>
                </F>
                {SOCIAL_KEYS.map((k) => (
                  <F key={k} label={SOCIAL_LABEL[k]} error={pErr[`social_links.${k}`]}>
                    <Input value={profile.data.social_links[k]} onChange={(e) => profile.setData('social_links', { ...profile.data.social_links, [k]: e.target.value })} />
                  </F>
                ))}
              </div>
              <Button type='submit' disabled={profile.processing}>{profile.processing ? 'Saving…' : 'Save profile'}</Button>
            </CardContent>
          </Card>
        </form>

        <div className='space-y-6'>
          <form onSubmit={(e) => { e.preventDefault(); payout.post('/partners/profile/payout-method', { preserveScroll: true }) }}>
            <Card>
              <CardHeader>
                <CardTitle>Payout method</CardTitle>
                <CardDescription>Stored encrypted. Used for every payout you request; each request keeps a copy of what was on file.</CardDescription>
              </CardHeader>
              <CardContent className='space-y-4'>
                <F label='Method' error={yErr.type}>
                  <select className='h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm' value={payout.data.type} onChange={(e) => payout.setData('type', e.target.value as 'bank' | 'other')}>
                    <option value='bank'>Bank transfer (IBAN)</option>
                    <option value='other'>Other</option>
                  </select>
                </F>
                {payout.data.type === 'bank' ? (
                  <div className='grid gap-4 sm:grid-cols-2'>
                    <F label='Bank name *' error={yErr.bank_name}><Input value={payout.data.bank_name} onChange={(e) => payout.setData('bank_name', e.target.value)} /></F>
                    <F label='Account holder *' error={yErr.holder_name}><Input value={payout.data.holder_name} onChange={(e) => payout.setData('holder_name', e.target.value)} /></F>
                    <div className='sm:col-span-2'>
                      <F label='IBAN *' error={yErr.iban}><Input className='font-mono' value={payout.data.iban} onChange={(e) => payout.setData('iban', e.target.value.toUpperCase())} /></F>
                    </div>
                  </div>
                ) : (
                  <F label='Details *' error={yErr.details}><Input placeholder='e.g. STC Pay number, PayPal email…' value={payout.data.details} onChange={(e) => payout.setData('details', e.target.value)} /></F>
                )}
                <Button type='submit' disabled={payout.processing}>{payout.processing ? 'Saving…' : 'Save payout method'}</Button>
              </CardContent>
            </Card>
          </form>

          <Card>
            <CardHeader><CardTitle>Verification</CardTitle></CardHeader>
            <CardContent className='space-y-1 text-sm text-muted-foreground'>
              <p>ID document: <span className='text-foreground'>{partner.has_id_document ? 'on file' : 'not provided'}</span></p>
              <p>Terms accepted: <span className='text-foreground'>{partner.terms_version ?? '—'} on {formatDate(partner.terms_accepted_at)}</span></p>
              <p>To change your email or password, use the account settings from the profile menu.</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </PartnerShell>
  )
}

function F({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <div className='grid gap-1.5'>
      <Label>{label}</Label>
      {children}
      {error && <p className='text-xs text-destructive'>{error}</p>}
    </div>
  )
}
