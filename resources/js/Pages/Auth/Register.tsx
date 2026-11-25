import { Head, Link, useForm } from '@inertiajs/react'
import { FormEventHandler, ReactNode, useEffect, useState } from 'react'
import { ArrowLeft, Check, Copy, KeyRound, PackageOpen, Sparkles, Truck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { authSplitLayout } from '@/components/auth/auth-split-layout'
import { generateStrongPassword } from '@/lib/password-utils'
import { checkSignupPassword } from '@/lib/signup-password'
import { PasswordStrength } from '@/components/auth/password-strength'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PasswordInput } from '@/components/password-input'

const BRAND = '#f26722'
const BRAND_HOVER = '#d9591c'

type ClientType = 'dropshipper' | 'fulfilment'

const SERVICES: {
  value: ClientType
  title: string
  description: string
  icon: typeof Truck
}[] = [
  {
    value: 'dropshipper',
    title: 'Dropshipping',
    description: 'Sell our products, we ship them',
    icon: Truck,
  },
  {
    value: 'fulfilment',
    title: 'Fulfilment',
    description: 'Store your stock in our warehouse',
    icon: PackageOpen,
  },
]

const inputClass =
  'h-12 rounded-lg border-input px-3.5 focus-visible:border-2 focus-visible:border-foreground focus-visible:ring-0'
const passwordClass =
  '[&>input]:h-12 [&>input]:rounded-lg [&>input]:border-input [&>input]:px-3.5 [&>input]:focus-visible:border-2 [&>input]:focus-visible:border-foreground [&>input]:focus-visible:ring-0'

const STEPS = [
  {
    title: 'Service',
    description: 'First, tell us which service you need.',
    fields: ['client_type'],
  },
  {
    title: 'Business',
    description: 'Tell us about you and your business.',
    fields: ['name', 'company_name', 'phone', 'city'],
  },
  {
    title: 'Account',
    description: 'Set the email and password you will sign in with.',
    fields: ['email', 'password', 'password_confirmation'],
  },
] as const

const LAST_STEP = STEPS.length - 1

/** name@domain.tld: no spaces, one @, and a dot with at least two letters after it. */
const EMAIL_FORMAT = /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)*\.[a-z]{2,}$/i

/**
 * What survives a refresh: the step and everything typed so far, except the
 * passwords, which are never written anywhere. Session storage is per tab and
 * gone when the tab closes.
 */
const DRAFT_KEY = 'ksadrop:signup-draft'
const DRAFT_FIELDS = ['client_type', 'name', 'company_name', 'phone', 'city', 'email'] as const

type Draft = { step: number; data: Partial<Record<(typeof DRAFT_FIELDS)[number], string>> }

function readDraft(): Draft | null {
  try {
    const raw = JSON.parse(sessionStorage.getItem(DRAFT_KEY) ?? 'null')
    if (!raw || typeof raw !== 'object') return null

    const data: Draft['data'] = {}
    for (const field of DRAFT_FIELDS) {
      if (typeof raw.data?.[field] === 'string') data[field] = raw.data[field]
    }
    const step = Number.isInteger(raw.step) ? Math.min(Math.max(raw.step, 0), LAST_STEP) : 0

    return { step, data }
  } catch {
    return null
  }
}

function clearDraft() {
  try {
    sessionStorage.removeItem(DRAFT_KEY)
  } catch {
    // Storage blocked: there is nothing to clear.
  }
}

function Field({
  id,
  label,
  required,
  error,
  children,
}: {
  id: string
  label: string
  required?: boolean
  error?: string
  children: ReactNode
}) {
  return (
    <div>
      <div className='group relative'>
        <Label
          htmlFor={id}
          className='absolute -top-2 left-2.5 z-10 bg-background px-1 text-xs font-medium text-muted-foreground transition-colors group-focus-within:text-foreground'
        >
          {label} {required && <span style={{ color: BRAND }}>*</span>}
        </Label>
        {children}
      </div>
      {error && <p className='mt-1.5 text-sm text-destructive'>{error}</p>}
    </div>
  )
}

export default function Register() {
  // Read once, on the first render.
  const [draft] = useState(readDraft)

  const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
    name: '',
    company_name: '',
    email: '',
    phone: '',
    city: '',
    client_type: '' as ClientType | '',
    password: '',
    password_confirmation: '',
    website: '', // honeypot, hidden from people
    ...draft?.data,
  })

  const [step, setStep] = useState(draft?.step ?? 0)
  // Bumped by the generator: remounts the password inputs with the value shown.
  const [generation, setGeneration] = useState(0)
  const [copied, setCopied] = useState(false)
  // The "save your password" reminder, shown after each generated password
  // until it is copied or closed.
  const [reminderClosed, setReminderClosed] = useState(false)

  const strength = checkSignupPassword(data.password, data.name, data.email)
  const confirmMatches =
    data.password_confirmation !== '' && data.password === data.password_confirmation

  /** What is still wrong on one step. The server checks everything again. */
  const stepProblems = (index: number) => {
    const found: Partial<Record<keyof typeof data, string>> = {}

    if (index === 0 && !data.client_type) {
      found.client_type = 'Choose the service you need.'
    }
    if (index === 1) {
      if (!data.name.trim()) found.name = 'Enter your full name.'
      if (!data.company_name.trim()) found.company_name = 'Enter your company or store name.'
      const phone = data.phone.trim()
      if (!/^\d{8,15}$/.test(phone)) found.phone = 'Enter a valid phone number.'
    }
    if (index === 2) {
      if (!EMAIL_FORMAT.test(data.email.trim())) found.email = 'Enter a valid email address.'
      if (!strength.valid) found.password = 'Your password does not meet all the rules yet.'
      if (!confirmMatches) found.password_confirmation = 'Passwords do not match.'
    }

    return found
  }

  const submit: FormEventHandler = (e) => {
    e.preventDefault()

    const problems = stepProblems(step)
    clearErrors()
    if (Object.keys(problems).length > 0) {
      setError(problems as Record<keyof typeof data, string>)
      return
    }

    if (step < LAST_STEP) {
      setStep(step + 1)
      return
    }

    post(route('register'), {
      // Done: the next visit starts fresh.
      onSuccess: () => clearDraft(),
      // Open the first step that has something the server refused.
      onError: (failed) => {
        const index = STEPS.findIndex((s) => s.fields.some((field) => field in failed))
        if (index !== -1) setStep(index)
      },
    })
  }

  // A refresh comes back to the same step, unless an earlier one is now
  // incomplete (only the client type, say, survived): then to that one.
  useEffect(() => {
    if (step === 0) return
    const incomplete = STEPS.findIndex(
      (_, index) => index < step && Object.keys(stepProblems(index)).length > 0,
    )
    if (incomplete !== -1) setStep(incomplete)
    // Only once, for the restored step.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Keep the draft up to date as the person types and moves between steps.
  useEffect(() => {
    try {
      sessionStorage.setItem(
        DRAFT_KEY,
        JSON.stringify({
          step,
          data: Object.fromEntries(DRAFT_FIELDS.map((field) => [field, data[field]])),
        }),
      )
    } catch {
      // Storage full or blocked: the form still works, it just won't survive a refresh.
    }
  }, [step, data.client_type, data.name, data.company_name, data.phone, data.city, data.email])

  const generatePassword = () => {
    const password = generateStrongPassword(16)
    setData((current) => ({ ...current, password, password_confirmation: password }))
    clearErrors('password', 'password_confirmation')
    setGeneration((n) => n + 1)
    setCopied(false)
    setReminderClosed(false)
  }

  const canCopy = generation > 0 && data.password !== ''

  const copyPassword = async () => {
    try {
      await navigator.clipboard.writeText(data.password)
      setCopied(true)
      // Long enough to read "Copied", then out of the way.
      setTimeout(() => setReminderClosed(true), 1500)
    } catch {
      // No clipboard access: the password is visible and can be copied by hand.
    }
  }

  const servicesError = errors.client_type

  // Checked as it is typed, but only said once typing pauses (or the field is
  // left), so "nam" is not called wrong halfway through "name@shop.sa".
  const emailValid = EMAIL_FORMAT.test(data.email.trim())
  const [emailSettled, setEmailSettled] = useState(false)
  useEffect(() => {
    setEmailSettled(false)
    if (data.email.trim() === '') return
    const timer = setTimeout(() => setEmailSettled(true), 600)
    return () => clearTimeout(timer)
  }, [data.email])
  const emailFormatError =
    data.email.trim() !== '' && !emailValid && emailSettled
      ? 'Enter a valid email address.'
      : undefined
  const emailError = errors.email ?? emailFormatError
  const showReminder = step === LAST_STEP && canCopy && !reminderClosed

  return (
    <>
      <Head title='Create an account' />

      {/* Frosted glass, like the stock alert cards. It stays until the
          password is copied: there is no other way to close it. */}
      {showReminder && (
        <div
          role='status'
          className='fixed bottom-4 end-4 z-50 w-[min(17rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-[#f26722]/25 bg-[#f26722]/12 text-foreground shadow-lg backdrop-blur-md backdrop-saturate-150 animate-in fade-in slide-in-from-bottom-2 duration-200 sm:bottom-6 sm:end-6'
        >
          <div className='flex items-start gap-2.5 p-3'>
            <div
              className='flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#f26722]/15'
              style={{ color: BRAND }}
            >
              <KeyRound className='h-3.5 w-3.5' />
            </div>
            <div className='min-w-0 flex-1'>
              <div
                className='text-[10px] font-semibold tracking-wide uppercase'
                style={{ color: BRAND }}
              >
                Save your password
              </div>
              <div className='text-[13px] font-semibold leading-5'>Copy it before you continue</div>
              <div className='text-[11px] text-muted-foreground'>
                Keep it somewhere safe. You will need it to sign in.
              </div>
              <button
                type='button'
                onClick={copyPassword}
                className='mt-2 inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[11px] font-semibold text-white transition-opacity hover:opacity-90'
                style={{ backgroundColor: BRAND }}
              >
                {copied ? <Check className='h-3.5 w-3.5' /> : <Copy className='h-3.5 w-3.5' />}
                {copied ? 'Copied' : 'Copy password'}
              </button>
            </div>
          </div>
        </div>
      )}
      <div className='w-full max-w-sm'>
        <div className='mb-7'>
          <h1 className='text-2xl font-semibold tracking-tight'>
            Start selling with KSA DROP <span aria-hidden>🚀</span>
          </h1>
          <p className='mt-1.5 text-sm text-muted-foreground'>{STEPS[step].description}</p>
        </div>

        <ol className='mb-7 grid grid-cols-3 gap-2'>
          {STEPS.map((item, index) => (
            <li key={item.title} aria-current={index === step ? 'step' : undefined}>
              <span
                className={cn('block h-1 rounded-full', index > step && 'bg-muted')}
                style={index <= step ? { backgroundColor: BRAND } : undefined}
              />
              <span
                className={cn(
                  'mt-1.5 block text-xs',
                  index === step ? 'font-medium text-foreground' : 'text-muted-foreground',
                )}
              >
                {index + 1}. {item.title}
              </span>
            </li>
          ))}
        </ol>

        <form onSubmit={submit} className='space-y-5' noValidate>
          {step === 0 && (
            <>
              <fieldset>
                <legend className='mb-2 text-xs font-medium text-muted-foreground'>
                  Service you need <span style={{ color: BRAND }}>*</span>
                </legend>
                <div role='radiogroup' className='grid grid-cols-2 gap-3'>
                  {SERVICES.map((service) => {
                    const selected = data.client_type === service.value
                    return (
                      <button
                        key={service.value}
                        type='button'
                        role='radio'
                        aria-checked={selected}
                        onClick={() => setData('client_type', service.value)}
                        className={cn(
                          'relative flex flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors',
                          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-foreground',
                          selected ? 'bg-[#f26722]/5' : 'border-input hover:bg-muted/50',
                          servicesError && !selected && 'border-destructive',
                        )}
                        style={
                          selected
                            ? {
                                borderColor: BRAND,
                              }
                            : undefined
                        }
                      >
                        <service.icon
                          className='h-5 w-5'
                          style={{
                            color: selected ? BRAND : undefined,
                          }}
                        />
                        <span className='text-sm font-medium'>{service.title}</span>
                        <span className='text-xs leading-snug text-muted-foreground'>
                          {service.description}
                        </span>
                        {selected && (
                          <span
                            className='absolute right-2 top-2 flex h-4 w-4 items-center justify-center rounded-full text-white'
                            style={{
                              backgroundColor: BRAND,
                            }}
                          >
                            <Check className='h-3 w-3' />
                          </span>
                        )}
                      </button>
                    )
                  })}
                </div>
                {servicesError && (
                  <p className='mt-1.5 text-sm text-destructive'>{servicesError}</p>
                )}
              </fieldset>
            </>
          )}

          {step === 1 && (
            <>
              <Field id='name' label='Full name' required error={errors.name}>
                <Input
                  id='name'
                  value={data.name}
                  autoComplete='name'
                  autoFocus
                  onChange={(e) => setData('name', e.target.value)}
                  className={cn(inputClass, errors.name && 'border-destructive')}
                />
              </Field>

              <Field
                id='company_name'
                label='Company / store name'
                required
                error={errors.company_name}
              >
                <Input
                  id='company_name'
                  value={data.company_name}
                  autoComplete='organization'
                  onChange={(e) => setData('company_name', e.target.value)}
                  className={cn(inputClass, errors.company_name && 'border-destructive')}
                />
              </Field>

              <div className='grid grid-cols-2 gap-3'>
                <Field id='phone' label='Phone' required error={errors.phone}>
                  <Input
                    id='phone'
                    type='tel'
                    inputMode='tel'
                    placeholder='05XXXXXXXX'
                    value={data.phone}
                    autoComplete='tel'
                    onChange={(e) =>
                      // Digits only, typed or pasted. Arabic-Indic digits become 0-9.
                      setData(
                        'phone',
                        e.target.value
                          .replace(/[\u0660-\u0669\u06F0-\u06F9]/g, (digit) =>
                            String(digit.charCodeAt(0) & 0xf),
                          )
                          .replace(/\D/g, '')
                          .slice(0, 15),
                      )
                    }
                    className={cn(inputClass, errors.phone && 'border-destructive')}
                  />
                </Field>
                <Field id='city' label='City' error={errors.city}>
                  <Input
                    id='city'
                    value={data.city}
                    autoComplete='address-level2'
                    onChange={(e) => setData('city', e.target.value)}
                    className={cn(inputClass, errors.city && 'border-destructive')}
                  />
                </Field>
              </div>
            </>
          )}

          {step === 2 && (
            <>
              <Field id='email' label='Email ID' required error={emailError}>
                <div className='relative'>
                  <Input
                    id='email'
                    type='email'
                    value={data.email}
                    autoComplete='username'
                    autoFocus
                    autoCapitalize='none'
                    spellCheck={false}
                    aria-invalid={!!emailError}
                    onBlur={() => data.email.trim() !== '' && setEmailSettled(true)}
                    onChange={(e) => {
                      setData('email', e.target.value)
                      if (errors.email) clearErrors('email')
                    }}
                    className={cn(inputClass, 'pe-10', emailError && 'border-destructive')}
                  />
                  {emailValid && !errors.email && (
                    <Check
                      className='pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600 dark:text-green-500'
                      aria-hidden
                    />
                  )}
                </div>
              </Field>

              <Field id='password' label='Password' required error={errors.password}>
                {/* The field, and the generator beside it. */}
                <div className='flex items-center gap-2'>
                  <div className='relative min-w-0 flex-1'>
                    <PasswordInput
                      key={generation}
                      defaultVisible={generation > 0}
                      id='password'
                      value={data.password}
                      autoComplete='new-password'
                      onChange={(e) => setData('password', e.target.value)}
                      className={cn(
                        passwordClass,
                        // Room for the icons inside the field, beside show/hide.
                        canCopy ? '[&>input]:pe-16' : '[&>input]:pe-10',
                        errors.password && '[&>input]:border-destructive',
                      )}
                    />
                    <div className='absolute inset-y-0 right-8 flex items-center gap-0.5'>
                      {canCopy && (
                        <button
                          type='button'
                          onClick={copyPassword}
                          title={copied ? 'Copied' : 'Copy password'}
                          aria-label={copied ? 'Password copied' : 'Copy password'}
                          className='flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground'
                        >
                          {copied ? <Check className='h-4 w-4' /> : <Copy className='h-4 w-4' />}
                        </button>
                      )}
                    </div>
                  </div>
                  <button
                    type='button'
                    onClick={generatePassword}
                    title='Generate strong password'
                    aria-label='Generate strong password'
                    className='flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-input transition-colors hover:bg-muted'
                    style={{ color: BRAND }}
                  >
                    <Sparkles className='h-5 w-5' />
                  </button>
                </div>
              </Field>

              <div>
                {/* Only once something is typed; a fresh field stays clean. */}
                {data.password !== '' && (
                  <div className='animate-in fade-in-0 slide-in-from-top-1 duration-300'>
                    <PasswordStrength result={strength} />
                  </div>
                )}
              </div>

              <Field
                id='password_confirmation'
                label='Confirm password'
                required
                error={errors.password_confirmation}
              >
                <PasswordInput
                  key={generation}
                  defaultVisible={generation > 0}
                  id='password_confirmation'
                  value={data.password_confirmation}
                  autoComplete='new-password'
                  onChange={(e) => setData('password_confirmation', e.target.value)}
                  className={cn(
                    passwordClass,
                    errors.password_confirmation && '[&>input]:border-destructive',
                  )}
                />
              </Field>
            </>
          )}

          {/* Honeypot: off-screen and skipped by keyboard and screen readers. */}
          <div aria-hidden className='absolute -left-[9999px] h-0 w-0 overflow-hidden'>
            <label htmlFor='website'>Website</label>
            <input
              id='website'
              name='website'
              tabIndex={-1}
              autoComplete='off'
              value={data.website}
              onChange={(e) => setData('website', e.target.value)}
            />
          </div>

          <div className='flex gap-3'>
            {step > 0 && (
              <button
                type='button'
                onClick={() => {
                  clearErrors()
                  setStep(step - 1)
                }}
                disabled={processing}
                className='flex h-12 items-center justify-center gap-1.5 rounded-lg border border-input px-5 text-sm font-medium transition-colors hover:bg-muted/60 disabled:opacity-60'
              >
                <ArrowLeft className='h-4 w-4' />
                Back
              </button>
            )}
            <button
              type='submit'
              disabled={processing}
              className='flex h-12 flex-1 items-center justify-center rounded-lg text-sm font-semibold text-white shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60'
              style={{ backgroundColor: BRAND }}
              onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = BRAND_HOVER)}
              onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = BRAND)}
            >
              {step < LAST_STEP ? 'Continue' : processing ? 'Creating account…' : 'Create account'}
            </button>
          </div>

          <p className='text-center text-sm text-muted-foreground'>
            Already have an account?{' '}
            <Link
              href={route('login')}
              prefetch
              className='font-medium hover:underline'
              style={{ color: BRAND }}
            >
              Sign in
            </Link>
          </p>
        </form>
      </div>
    </>
  )
}

Register.layout = authSplitLayout
