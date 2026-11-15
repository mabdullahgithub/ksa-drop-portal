import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, router, useForm } from '@inertiajs/react'
import { ArrowLeft, ArrowRight, Briefcase, Check, CheckCircle2, FileText, Megaphone, ShieldCheck, Upload, Users, X } from 'lucide-react'
import { PublicShell, btnPrimary, btnSecondary, fieldClass, labelClass, FieldError } from '@/features/partners/public-shell'
import { AUDIENCE_SIZES, COUNTRIES, SOCIAL_KEYS, SOCIAL_LABEL, type ProgramSummary } from '@/features/partners/types'

type Props = {
  program: ProgramSummary
  redirectToPortalJoin?: boolean
  applied?: boolean
}

const STEPS = [
  { key: 'type', label: 'Type', optional: false },
  { key: 'account', label: 'Account', optional: false },
  { key: 'profile', label: 'Profile', optional: true },
  { key: 'identity', label: 'Identity', optional: true },
  { key: 'finish', label: 'Finish', optional: false },
] as const

const TYPE_CARDS = [
  { value: 'agency', icon: Briefcase, title: 'Agency / freelancer', body: 'You build, run or market Shopify stores for clients.' },
  { value: 'influencer', icon: Megaphone, title: 'Influencer / community', body: 'You create content, teach, or run a group about e-commerce.' },
] as const

type Errs = Record<string, string | undefined>

export default function Apply({ program, redirectToPortalJoin, applied }: Props) {
  const initialType = useMemo(() => {
    const t = new URLSearchParams(window.location.search).get('type')
    return t === 'agency' || t === 'influencer' ? t : ''
  }, [])

  const [step, setStep] = useState(initialType ? 1 : 0)
  const [furthest, setFurthest] = useState(initialType ? 1 : 0)
  const [clientErrors, setClientErrors] = useState<Errs>({})
  const topRef = useRef<HTMLDivElement>(null)

  const form = useForm({
    type: initialType as '' | 'agency' | 'influencer',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    whatsapp: '',
    country: 'SA',
    city: '',
    display_name: '',
    company_name: '',
    website: '',
    social_links: Object.fromEntries(SOCIAL_KEYS.map((k) => [k, ''])) as Record<(typeof SOCIAL_KEYS)[number], string>,
    audience_size: '',
    promotion_plan: '',
    motivation: '',
    id_document_type: 'national_id',
    id_document: null as File | null,
    id_document_back: null as File | null,
    logo: null as File | null,
    code: '',
    payout_method: { type: '', bank_name: '', holder_name: '', iban: '', details: '' },
    terms: false as boolean,
    website_url_confirm: '',
  })
  const { data, setData, post, processing, errors } = form
  const allErrors: Errs = { ...(errors as Errs), ...clientErrors }

  // A server-side error belongs to one step: jump back to it.
  useEffect(() => {
    const keys = Object.keys(errors)
    if (!keys.length) return
    const at = (fields: string[]) => keys.some((k) => fields.some((f) => k === f || k.startsWith(f + '.')))
    if (at(['type'])) setStep(0)
    else if (at(['name', 'email', 'password', 'phone', 'whatsapp', 'country', 'city'])) setStep(1)
    else if (at(['display_name', 'company_name', 'website', 'social_links', 'audience_size', 'promotion_plan', 'motivation'])) setStep(2)
    else if (at(['id_document', 'id_document_back', 'id_document_type'])) setStep(3)
    else setStep(4)
  }, [errors])

  useEffect(() => {
    topRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }, [step])

  // After a successful submit: show "done" for 5 seconds, then to the partner portal.
  useEffect(() => {
    if (!applied) return
    const t = setTimeout(() => router.visit('/partners/dashboard'), 5000)
    return () => clearTimeout(t)
  }, [applied])

  if (applied) {
    return (
      <PublicShell title='Application received' description='Your KSA Drop partner application is in.'>
        <section className='mx-auto flex max-w-md flex-col items-center py-24 text-center'>
          <span className='flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 ring-8 ring-emerald-500/5 dark:text-emerald-400'>
            <CheckCircle2 className='h-8 w-8' />
          </span>
          <h1 className='mt-6 text-3xl font-semibold tracking-tight'>You&apos;re all done</h1>
          <p className='mt-3 text-slate-600 dark:text-slate-400'>
            Your application is in. We&apos;ll email you as soon as it&apos;s reviewed, usually within two business days. Meanwhile, your partner portal is ready to explore.
          </p>
          <div className='mt-8 h-1 w-48 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800'>
            <div className='h-full origin-left animate-[partner-countdown_5s_linear_forwards] rounded-full bg-emerald-500' />
          </div>
          <p className='mt-3 text-xs text-slate-500'>Taking you to your partner portal…</p>
          <Link href='/partners/dashboard' className='mt-6 text-sm font-medium underline underline-offset-4'>Go now</Link>
          <style>{'@keyframes partner-countdown{from{transform:scaleX(0)}to{transform:scaleX(1)}}'}</style>
        </section>
      </PublicShell>
    )
  }

  if (redirectToPortalJoin) {
    return (
      <PublicShell title='Join the partner program' description='Join the KSA Drop partner program from your portal.'>
        <section className='py-24 text-center'>
          <h1 className='text-3xl font-semibold tracking-tight'>You already have a KSA Drop account</h1>
          <p className='mx-auto mt-4 max-w-md text-slate-600 dark:text-slate-400'>
            Sellers join the partner program from their portal, so your details carry over. It takes a minute.
          </p>
          <Link href='/portal/partner/join' className={btnPrimary + ' mt-8'}>
            Join from my portal <ArrowRight className='h-4 w-4' />
          </Link>
        </section>
      </PublicShell>
    )
  }

  const validate = (i: number): Errs => {
    const e: Errs = {}
    if (i === 0 && !data.type) e.type = 'Choose the option that fits you best.'
    if (i === 1) {
      if (!data.name.trim()) e.name = 'Enter your full name.'
      if (!/^\S+@\S+\.\S+$/.test(data.email)) e.email = 'Enter a valid email address.'
      if (data.password.length < 8) e.password = 'Use at least 8 characters.'
      if (data.password !== data.password_confirmation) e.password_confirmation = 'Passwords do not match.'
      if (!data.phone.trim()) e.phone = 'Enter a phone number.'
    }
    if (i === 2 && data.website && !/^https?:\/\//i.test(data.website)) e.website = 'Start with https://'
    if (i === 4 && !data.terms) e.terms = 'Please accept the terms to continue.'
    return e
  }

  const go = (to: number) => {
    setClientErrors({})
    setStep(to)
    setFurthest((f) => Math.max(f, to))
  }

  const next = () => {
    const e = validate(step)
    setClientErrors(e)
    if (Object.keys(e).length) return
    go(Math.min(step + 1, STEPS.length - 1))
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    const err = validate(4)
    setClientErrors(err)
    if (Object.keys(err).length) return
    post('/partners/apply', { forceFormData: true })
  }

  const current = STEPS[step]
  const set = (k: keyof typeof data, v: unknown) => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    setData(k as any, v as any)
    if (clientErrors[k as string]) setClientErrors((c) => ({ ...c, [k as string]: undefined }))
  }

  return (
    <PublicShell title='Become a partner' description='Apply to the KSA Drop partner program: agencies, freelancers, influencers and communities.'>
      <div ref={topRef} className='scroll-mt-24 py-10 sm:py-14'>
        <form onSubmit={submit} className='mx-auto max-w-2xl'>
          <p className='text-center text-sm font-medium text-orange-600 dark:text-orange-400'>Become a partner</p>

          <Progress step={step} furthest={furthest} onJump={go} />

          <input type='text' name='website_url_confirm' value={data.website_url_confirm} onChange={(e) => setData('website_url_confirm', e.target.value)} className='hidden' tabIndex={-1} autoComplete='off' aria-hidden='true' />

          <div className='mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_20px_50px_-30px_rgba(15,23,42,0.35)] sm:p-9 dark:border-slate-800 dark:bg-slate-900/60'>
            <div className='mb-7'>
              <div className='flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400'>
                Step {step + 1} of {STEPS.length}
                {current.optional && <span className='rounded-full bg-slate-100 px-2 py-0.5 normal-case tracking-normal text-slate-500 dark:bg-slate-800 dark:text-slate-400'>Optional</span>}
              </div>
              <h1 className='mt-1.5 text-2xl font-semibold tracking-tight'>
                {step === 0 && 'Which describes you best?'}
                {step === 1 && 'Create your account'}
                {step === 2 && 'Tell us about your business'}
                {step === 3 && 'Verify your identity'}
                {step === 4 && 'Almost done'}
              </h1>
              <p className='mt-1.5 text-sm text-slate-500 dark:text-slate-400'>
                {step === 0 && 'This sets up your partner profile. You can change it later.'}
                {step === 1 && 'You will sign in with these details.'}
                {step === 2 && 'Everything here is optional. It helps us get to know you.'}
                {step === 3 && 'Optional now. Adding your ID speeds up review.'}
                {step === 4 && 'Choose how you want to be paid, then review and submit.'}
              </p>
            </div>

            <div key={step} className='animate-in fade-in slide-in-from-bottom-2 duration-300'>
              {step === 0 && (
                <div className='space-y-4'>
                  <div className='grid gap-3 sm:grid-cols-2'>
                    {TYPE_CARDS.map(({ value, icon: Icon, title, body }) => {
                      const on = data.type === value
                      return (
                        <button
                          type='button'
                          key={value}
                          onClick={() => set('type', value)}
                          className={
                            'group relative rounded-2xl border p-5 text-left transition-all ' +
                            (on
                              ? 'border-orange-500 bg-orange-50 ring-2 ring-orange-500/20 dark:bg-orange-500/10'
                              : 'border-slate-200 hover:-translate-y-0.5 hover:border-slate-400 dark:border-slate-800 dark:hover:border-slate-600')
                          }
                        >
                          {on && <Check className='absolute right-4 top-4 h-4 w-4 text-orange-600 dark:text-orange-400' />}
                          <Icon className='h-6 w-6 text-orange-600 dark:text-orange-400' />
                          <div className='mt-4 font-semibold'>{title}</div>
                          <div className='mt-1 text-sm text-slate-500 dark:text-slate-400'>{body}</div>
                        </button>
                      )
                    })}
                  </div>
                  <FieldError message={allErrors.type} />
                  <p className='flex items-center gap-2 pt-2 text-sm text-slate-500 dark:text-slate-400'>
                    <Users className='h-4 w-4' /> Already ship with KSA Drop?
                    <Link href='/login' className='font-medium text-slate-900 underline underline-offset-4 dark:text-slate-100'>Sign in to join from your portal</Link>
                  </p>
                </div>
              )}

              {step === 1 && (
                <div className='grid gap-5 sm:grid-cols-2'>
                  <Field label='Full name' required error={allErrors.name}>
                    <input className={fieldClass} value={data.name} onChange={(e) => set('name', e.target.value)} autoComplete='name' autoFocus />
                  </Field>
                  <Field label='Email' required error={allErrors.email}>
                    <input type='email' className={fieldClass} value={data.email} onChange={(e) => set('email', e.target.value)} autoComplete='email' />
                  </Field>
                  <Field label='Password' required error={allErrors.password} hint='At least 8 characters.'>
                    <input type='password' className={fieldClass} value={data.password} onChange={(e) => set('password', e.target.value)} autoComplete='new-password' />
                  </Field>
                  <Field label='Confirm password' required error={allErrors.password_confirmation}>
                    <input type='password' className={fieldClass} value={data.password_confirmation} onChange={(e) => set('password_confirmation', e.target.value)} autoComplete='new-password' />
                  </Field>
                  <Field label='Phone' required error={allErrors.phone}>
                    <input className={fieldClass} placeholder='+966 5x xxx xxxx' value={data.phone} onChange={(e) => set('phone', e.target.value)} autoComplete='tel' />
                  </Field>
                  <Field label='WhatsApp' error={allErrors.whatsapp} hint='If different from your phone.'>
                    <input className={fieldClass} value={data.whatsapp} onChange={(e) => set('whatsapp', e.target.value)} />
                  </Field>
                  <Field label='Country' required error={allErrors.country}>
                    <select className={fieldClass} value={data.country} onChange={(e) => set('country', e.target.value)}>
                      {COUNTRIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                  </Field>
                  <Field label='City' error={allErrors.city}>
                    <input className={fieldClass} value={data.city} onChange={(e) => set('city', e.target.value)} />
                  </Field>
                </div>
              )}

              {step === 2 && (
                <div className='space-y-5'>
                  <div className='grid gap-5 sm:grid-cols-2'>
                    <Field label={data.type === 'agency' ? 'Agency / company name' : 'Brand or channel name'} error={allErrors.company_name}>
                      <input className={fieldClass} value={data.company_name} onChange={(e) => set('company_name', e.target.value)} />
                    </Field>
                    <Field label='Public display name' error={allErrors.display_name} hint='Shown on your referral page.'>
                      <input className={fieldClass} value={data.display_name} onChange={(e) => set('display_name', e.target.value)} />
                    </Field>
                    <Field label='Website' error={allErrors.website}>
                      <input className={fieldClass} placeholder='https://' value={data.website} onChange={(e) => set('website', e.target.value)} />
                    </Field>
                    <Field label='Audience size' error={allErrors.audience_size}>
                      <select className={fieldClass} value={data.audience_size} onChange={(e) => set('audience_size', e.target.value)}>
                        <option value=''>Select…</option>
                        {AUDIENCE_SIZES.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                      </select>
                    </Field>
                    {SOCIAL_KEYS.map((key) => (
                      <Field key={key} label={SOCIAL_LABEL[key]} error={allErrors[`social_links.${key}`]}>
                        <input
                          className={fieldClass}
                          placeholder={key === 'other' ? 'Any other link' : 'Profile link or @handle'}
                          value={data.social_links[key]}
                          onChange={(e) => setData('social_links', { ...data.social_links, [key]: e.target.value })}
                        />
                      </Field>
                    ))}
                  </div>
                  <Field label='How will you promote KSA Drop?' error={allErrors.promotion_plan}>
                    <textarea rows={3} className={fieldClass} placeholder='Content you will make, clients you will onboard, groups you will share in…' value={data.promotion_plan} onChange={(e) => set('promotion_plan', e.target.value)} />
                  </Field>
                  <Field label='Anything else we should know?' error={allErrors.motivation}>
                    <textarea rows={3} className={fieldClass} value={data.motivation} onChange={(e) => set('motivation', e.target.value)} />
                  </Field>
                </div>
              )}

              {step === 3 && (
                <div className='space-y-6'>
                  <Field label='Document type' error={allErrors.id_document_type}>
                    <div className='grid grid-cols-3 gap-2'>
                      {([['national_id', 'National ID'], ['iqama', 'Iqama'], ['passport', 'Passport']] as const).map(([v, l]) => (
                        <button
                          type='button'
                          key={v}
                          onClick={() => set('id_document_type', v)}
                          className={
                            'rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors ' +
                            (data.id_document_type === v
                              ? 'border-orange-500 bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300'
                              : 'border-slate-200 text-slate-600 hover:border-slate-400 dark:border-slate-800 dark:text-slate-400')
                          }
                        >
                          {l}
                        </button>
                      ))}
                    </div>
                  </Field>
                  <div className='grid gap-4 sm:grid-cols-2'>
                    <UploadCard label='Front side' file={data.id_document} onChange={(f) => set('id_document', f)} error={allErrors.id_document} />
                    <UploadCard
                      label='Back side'
                      file={data.id_document_back}
                      onChange={(f) => set('id_document_back', f)}
                      error={allErrors.id_document_back}
                      hint={data.id_document_type === 'passport' ? 'Not needed for a passport.' : undefined}
                    />
                  </div>
                  <p className='flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400'>
                    <ShieldCheck className='mt-0.5 h-4 w-4 shrink-0 text-emerald-600' />
                    JPG, PNG or PDF, up to 8 MB each. Stored privately; only our review team can open it.
                  </p>
                </div>
              )}

              {step === 4 && (
                <div className='space-y-7'>
                  <div className='grid gap-5 sm:grid-cols-2'>
                    <Field label='Preferred referral code' error={allErrors.code} hint='4–20 letters and digits. Leave empty and we make one.'>
                      <input className={fieldClass + ' font-mono uppercase'} maxLength={20} value={data.code} onChange={(e) => set('code', e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))} />
                    </Field>
                    <Field label='Logo or profile photo' error={allErrors.logo} hint='Shown on your referral page.'>
                      <FileInput file={data.logo} onChange={(f) => set('logo', f)} accept='image/*' />
                    </Field>
                    <Field label='Payout method' error={allErrors['payout_method.type']} hint='You can decide later.'>
                      <select className={fieldClass} value={data.payout_method.type} onChange={(e) => setData('payout_method', { ...data.payout_method, type: e.target.value })}>
                        <option value=''>Decide later</option>
                        <option value='bank'>Bank transfer (IBAN)</option>
                        <option value='other'>Other</option>
                      </select>
                    </Field>
                    {data.payout_method.type === 'bank' && (
                      <>
                        <Field label='Bank name'><input className={fieldClass} value={data.payout_method.bank_name} onChange={(e) => setData('payout_method', { ...data.payout_method, bank_name: e.target.value })} /></Field>
                        <Field label='Account holder'><input className={fieldClass} value={data.payout_method.holder_name} onChange={(e) => setData('payout_method', { ...data.payout_method, holder_name: e.target.value })} /></Field>
                        <Field label='IBAN'><input className={fieldClass + ' font-mono'} value={data.payout_method.iban} onChange={(e) => setData('payout_method', { ...data.payout_method, iban: e.target.value.toUpperCase() })} /></Field>
                      </>
                    )}
                    {data.payout_method.type === 'other' && (
                      <div className='sm:col-span-2'>
                        <Field label='Details'><textarea rows={2} className={fieldClass} placeholder='e.g. STC Pay number, PayPal email…' value={data.payout_method.details} onChange={(e) => setData('payout_method', { ...data.payout_method, details: e.target.value })} /></Field>
                      </div>
                    )}
                  </div>

                  <dl className='grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-950/60'>
                    <dt className='text-slate-500'>Type</dt><dd>{data.type === 'agency' ? 'Agency / freelancer' : 'Influencer / community'}</dd>
                    <dt className='text-slate-500'>Name</dt><dd>{data.name}</dd>
                    <dt className='text-slate-500'>Email</dt><dd className='truncate'>{data.email}</dd>
                    <dt className='text-slate-500'>ID</dt><dd>{data.id_document ? (data.id_document_back ? 'Front and back added' : 'Front added') : 'Not added'}</dd>
                  </dl>

                  <div>
                    <label className='flex items-start gap-3 text-sm'>
                      <input type='checkbox' className='mt-1 h-4 w-4 accent-orange-600' checked={data.terms} onChange={(e) => set('terms', e.target.checked)} />
                      <span className='text-slate-600 dark:text-slate-400'>
                        I confirm the information is accurate, I will not refer my own stores or accounts, and I accept the
                        partner program terms (version {program.terms_version}). Points have no cash value until a payout is
                        approved by KSA Drop.
                      </span>
                    </label>
                    <FieldError message={allErrors.terms} />
                  </div>
                </div>
              )}
            </div>

            <div className='mt-9 flex items-center justify-between gap-3'>
              <button type='button' onClick={() => go(step - 1)} disabled={step === 0} className={btnSecondary + ' disabled:invisible'}>
                <ArrowLeft className='h-4 w-4' /> Back
              </button>
              <div className='flex items-center gap-3'>
                {current.optional && step < STEPS.length - 1 && (
                  <button type='button' onClick={() => go(step + 1)} className='text-sm font-medium text-slate-500 hover:text-slate-900 dark:hover:text-slate-100'>
                    Skip for now
                  </button>
                )}
                {step < STEPS.length - 1 ? (
                  <button type='button' onClick={next} className={btnPrimary}>
                    Continue <ArrowRight className='h-4 w-4' />
                  </button>
                ) : (
                  <button type='submit' disabled={processing} className={btnPrimary}>
                    {processing ? 'Submitting…' : 'Submit application'} <CheckCircle2 className='h-4 w-4' />
                  </button>
                )}
              </div>
            </div>
          </div>

          <p className='mt-5 text-center text-xs text-slate-500 dark:text-slate-400'>
            Applications are reviewed within two business days.
          </p>
        </form>
      </div>
    </PublicShell>
  )
}

function Progress({ step, furthest, onJump }: { step: number; furthest: number; onJump: (i: number) => void }) {
  return (
    <ol className='mt-5 flex items-center justify-between'>
      {STEPS.map((s, i) => {
        const done = i < step
        const active = i === step
        const reachable = i <= furthest && i !== step
        return (
          <li key={s.key} className='flex flex-1 items-center last:flex-none'>
            <button
              type='button'
              disabled={!reachable}
              onClick={() => onJump(i)}
              className='flex flex-col items-center gap-1.5 disabled:cursor-default'
              aria-current={active ? 'step' : undefined}
            >
              <span
                className={
                  'flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold transition-all ' +
                  (done
                    ? 'bg-emerald-500 text-white'
                    : active
                      ? 'bg-slate-900 text-white ring-4 ring-orange-500/20 dark:bg-white dark:text-slate-900'
                      : 'bg-slate-100 text-slate-400 dark:bg-slate-800')
                }
              >
                {done ? <Check className='h-4 w-4' /> : i + 1}
              </span>
              <span className={'hidden text-xs font-medium sm:block ' + (active ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400')}>{s.label}</span>
            </button>
            {i < STEPS.length - 1 && (
              <span className='mx-2 mb-0 h-px flex-1 bg-slate-200 sm:mb-5 dark:bg-slate-800'>
                <span className={'block h-px bg-emerald-500 transition-all duration-500 ' + (done ? 'w-full' : 'w-0')} />
              </span>
            )}
          </li>
        )
      })}
    </ol>
  )
}

function Field({ label, hint, error, required, children }: { label: string; hint?: string; error?: string; required?: boolean; children: React.ReactNode }) {
  return (
    <div>
      <label className={labelClass}>
        {label}
        {required ? <span className='ml-0.5 text-orange-600'>*</span> : <span className='ml-1.5 text-xs font-normal text-slate-400'>optional</span>}
      </label>
      {children}
      {hint && !error && <p className='mt-1 text-xs text-slate-500 dark:text-slate-400'>{hint}</p>}
      <FieldError message={error} />
    </div>
  )
}

function FileInput({ file, onChange, accept }: { file: File | null; onChange: (f: File | null) => void; accept: string }) {
  return (
    <label className='flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm hover:border-slate-500 dark:border-slate-700'>
      <Upload className='h-4 w-4 shrink-0 text-slate-400' />
      <span className='truncate text-slate-600 dark:text-slate-400'>{file ? file.name : 'Choose a file…'}</span>
      <input type='file' accept={accept} className='hidden' onChange={(e) => onChange(e.target.files?.[0] ?? null)} />
    </label>
  )
}

function UploadCard({ label, file, onChange, error, hint }: { label: string; file: File | null; onChange: (f: File | null) => void; error?: string; hint?: string }) {
  const preview = useMemo(() => (file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null), [file])
  useEffect(() => () => { if (preview) URL.revokeObjectURL(preview) }, [preview])

  return (
    <div>
      <div className={labelClass}>{label}<span className='ml-1.5 text-xs font-normal text-slate-400'>optional</span></div>
      {file ? (
        <div className='relative flex h-40 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/60'>
          {preview ? (
            <img src={preview} alt={label} className='h-full w-full object-cover' />
          ) : (
            <div className='flex flex-col items-center gap-2 px-4 text-center text-sm text-slate-600 dark:text-slate-400'>
              <FileText className='h-7 w-7' />
              <span className='max-w-full truncate'>{file.name}</span>
            </div>
          )}
          <button
            type='button'
            onClick={() => onChange(null)}
            aria-label={`Remove ${label}`}
            className='absolute right-2 top-2 rounded-full bg-slate-900/70 p-1.5 text-white backdrop-blur transition-colors hover:bg-slate-900'
          >
            <X className='h-3.5 w-3.5' />
          </button>
        </div>
      ) : (
        <label className='flex h-40 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 text-center transition-colors hover:border-orange-500 hover:bg-orange-50/50 dark:border-slate-700 dark:hover:bg-orange-500/5'>
          <Upload className='h-5 w-5 text-slate-400' />
          <span className='text-sm font-medium text-slate-700 dark:text-slate-300'>Upload {label.toLowerCase()}</span>
          <span className='text-xs text-slate-400'>JPG, PNG or PDF</span>
          <input type='file' accept='image/*,.pdf' className='hidden' onChange={(e) => onChange(e.target.files?.[0] ?? null)} />
        </label>
      )}
      {hint && !error && <p className='mt-1 text-xs text-slate-500 dark:text-slate-400'>{hint}</p>}
      <FieldError message={error} />
    </div>
  )
}
