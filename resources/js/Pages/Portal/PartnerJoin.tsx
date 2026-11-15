import { Head, useForm } from '@inertiajs/react'
import { Handshake, Upload } from 'lucide-react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { ProgramTable, TierTable } from '@/features/partners/program-table'
import { formatPoints, type ProgramSummary } from '@/features/partners/types'

type Props = {
  program: ProgramSummary
  suggestedCode: string
  client: { company_name: string; phone: string | null; has_cr: boolean }
}

/** A client switching partner mode on. Their account details carry over. */
export default function PartnerJoin({ program, suggestedCode, client }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    code: suggestedCode,
    phone: client.phone ?? '',
    whatsapp: '',
    promotion_plan: '',
    motivation: '',
    id_document_type: 'national_id',
    id_document: null as File | null,
    terms: false as boolean,
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post('/portal/partner/join', { forceFormData: true })
  }

  const err = errors as Record<string, string | undefined>

  return (
    <AuthenticatedLayout>
      <Head title='Partner Program' />
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>
      <Main>
        <div className='mb-6'>
          <h1 className='flex items-center gap-2 text-2xl font-bold tracking-tight'>
            <Handshake className='h-6 w-6 text-orange-600' /> Partner Program
          </h1>
          <p className='text-muted-foreground'>Refer other sellers to KSA Drop and earn points on every delivery they ship.</p>
        </div>

        <div className='grid gap-6 lg:grid-cols-[1fr_360px]'>
          <form onSubmit={submit}>
            <Card>
              <CardHeader>
                <CardTitle>Join as {client.company_name}</CardTitle>
                <CardDescription>
                  Your company details carry over from your account. Tell us how you will refer sellers and pick your code.
                </CardDescription>
              </CardHeader>
              <CardContent className='space-y-5'>
                <div className='grid gap-4 sm:grid-cols-2'>
                  <div className='grid gap-1.5'>
                    <Label htmlFor='pj-code'>Referral code</Label>
                    <Input id='pj-code' className='font-mono uppercase' maxLength={20} value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))} />
                    <p className='text-xs text-muted-foreground'>4–20 letters and digits. Your link will be /r/{data.code || '…'}</p>
                    {err.code && <p className='text-xs text-destructive'>{err.code}</p>}
                  </div>
                  <div className='grid gap-1.5'>
                    <Label htmlFor='pj-phone'>Phone</Label>
                    <Input id='pj-phone' value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    {err.phone && <p className='text-xs text-destructive'>{err.phone}</p>}
                  </div>
                  <div className='grid gap-1.5'>
                    <Label htmlFor='pj-wa'>WhatsApp (if different)</Label>
                    <Input id='pj-wa' value={data.whatsapp} onChange={(e) => setData('whatsapp', e.target.value)} />
                  </div>
                </div>

                <div className='grid gap-1.5'>
                  <Label htmlFor='pj-plan'>How will you refer sellers? *</Label>
                  <Textarea id='pj-plan' rows={3} value={data.promotion_plan} onChange={(e) => setData('promotion_plan', e.target.value)} placeholder='Other store owners you know, a community you are in, your supplier network…' />
                  {err.promotion_plan && <p className='text-xs text-destructive'>{err.promotion_plan}</p>}
                </div>

                <div className='grid gap-1.5'>
                  <Label htmlFor='pj-motivation'>Anything else you want us to know</Label>
                  <Textarea id='pj-motivation' rows={3} value={data.motivation} onChange={(e) => setData('motivation', e.target.value)} />
                </div>

                {!client.has_cr && (
                  <div className='grid gap-4 sm:grid-cols-2'>
                    <div className='grid gap-1.5'>
                      <Label htmlFor='pj-idtype'>ID document type</Label>
                      <select id='pj-idtype' className='h-9 rounded-md border border-input bg-transparent px-3 text-sm' value={data.id_document_type} onChange={(e) => setData('id_document_type', e.target.value)}>
                        <option value='national_id'>National ID</option>
                        <option value='iqama'>Iqama</option>
                        <option value='passport'>Passport</option>
                      </select>
                    </div>
                    <div className='grid gap-1.5'>
                      <Label>ID document</Label>
                      <label className='flex h-9 cursor-pointer items-center gap-2 rounded-md border border-dashed border-input px-3 text-sm text-muted-foreground'>
                        <Upload className='h-4 w-4' />
                        <span className='truncate'>{data.id_document?.name ?? 'Choose a file…'}</span>
                        <input type='file' accept='image/*,.pdf' className='hidden' onChange={(e) => setData('id_document', e.target.files?.[0] ?? null)} />
                      </label>
                      <p className='text-xs text-muted-foreground'>We have no commercial registration on file for you, so an ID helps us approve faster.</p>
                      {err.id_document && <p className='text-xs text-destructive'>{err.id_document}</p>}
                    </div>
                  </div>
                )}

                <label className='flex items-start gap-3 text-sm'>
                  <input type='checkbox' className='mt-1 h-4 w-4' checked={data.terms} onChange={(e) => setData('terms', e.target.checked)} />
                  <span className='text-muted-foreground'>
                    I will not refer my own stores or accounts, and I accept the partner program terms (version {program.terms_version}).
                    Points have no cash value until a payout is approved by KSA Drop.
                  </span>
                </label>
                {err.terms && <p className='text-xs text-destructive'>{err.terms}</p>}

                <Button type='submit' disabled={processing || !data.terms || data.promotion_plan.trim().length < 20}>
                  {processing ? 'Joining…' : 'Join the partner program'}
                </Button>
              </CardContent>
            </Card>
          </form>

          <div className='space-y-4'>
            <Card>
              <CardHeader><CardTitle className='text-base'>What you earn</CardTitle></CardHeader>
              <CardContent>
                <ProgramTable program={program} compact />
                <p className='mt-3 text-xs text-muted-foreground'>Redeem from {formatPoints(program.min_redeem_points)} points. 1 point ≈ 1 SAR at payout.</p>
              </CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className='text-base'>Tiers</CardTitle></CardHeader>
              <CardContent><TierTable program={program} /></CardContent>
            </Card>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
