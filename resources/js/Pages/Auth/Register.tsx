import { Head, Link, useForm } from '@inertiajs/react'
import { FormEventHandler, ReactNode, useState } from 'react'
import { ArrowLeft, Check, Copy, PackageOpen, Sparkles, Truck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { generateStrongPassword } from '@/lib/password-utils'
import { checkSignupPassword } from '@/lib/signup-password'
import { PasswordStrength } from '@/components/auth/password-strength'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PasswordInput } from '@/components/password-input'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'

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
  })

  const [step, setStep] = useState(0)
  // Bumped by the generator: remounts the password inputs with the value shown.
  const [generation, setGeneration] = useState(0)
  const [copied, setCopied] = useState(false)

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
      if (!/^\+?[\d\s-]{8,20}$/.test(phone)) found.phone = 'Enter a valid phone number.'
    }
    if (index === 2) {
      if (!/^\S+@\S+\.\S+$/.test(data.email.trim())) found.email = 'Enter a valid email.'
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
      // Open the first step that has something the server refused.
      onError: (failed) => {
        const index = STEPS.findIndex((s) => s.fields.some((field) => field in failed))
        if (index !== -1) setStep(index)
      },
    })
  }

  const generatePassword = () => {
    const password = generateStrongPassword(16)
    setData((current) => ({ ...current, password, password_confirmation: password }))
    clearErrors('password', 'password_confirmation')
    setGeneration((n) => n + 1)
    setCopied(false)
  }

  const copyPassword = async () => {
    try {
      await navigator.clipboard.writeText(data.password)
      setCopied(true)
    } catch {
      // No clipboard access: the password is visible and can be copied by hand.
    }
  }

  const servicesError = errors.client_type

  return (
    <ThemeProvider>
      <Head title='Create an account' />
      <div className='relative grid min-h-svh grid-cols-1 lg:h-svh lg:grid-cols-[1.6fr_1fr] lg:overflow-hidden'>
        {/* Brand / illustration panel */}
        <div className='relative hidden flex-col bg-muted/60 lg:flex'>
          <div className='absolute left-8 top-7'>
            <AuthLogo />
          </div>
          <div className='flex flex-1 items-center justify-center p-10'>
            <video
              className='h-auto w-full max-w-2xl select-none object-contain'
              autoPlay
              loop
              muted
              playsInline
              aria-label='KSA Drop'
            >
              <source src='/images/login/drone-with-parcel.mov' type='video/quicktime' />
              <source src='/images/login/drone-with-parcel.webm' type='video/webm' />
            </video>
          </div>
        </div>

        {/* Form panel */}
        <div className='relative flex flex-col bg-background lg:overflow-y-auto'>
          <div className='flex items-center px-6 pt-6 lg:hidden'>
            <AuthLogo />
          </div>

          <div className='absolute right-6 top-6'>
            <ThemeSwitch />
          </div>

          <div className='flex flex-1 items-center justify-center px-6 py-12'>
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
                          onChange={(e) => setData('phone', e.target.value)}
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
                    <Field id='email' label='Email ID' required error={errors.email}>
                      <Input
                        id='email'
                        type='email'
                        value={data.email}
                        autoComplete='username'
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                        className={cn(inputClass, errors.email && 'border-destructive')}
                      />
                    </Field>

                    <Field id='password' label='Password' required error={errors.password}>
                      <PasswordInput
                        key={generation}
                        defaultVisible={generation > 0}
                        id='password'
                        value={data.password}
                        autoComplete='new-password'
                        onChange={(e) => setData('password', e.target.value)}
                        className={cn(
                          passwordClass,
                          errors.password && '[&>input]:border-destructive',
                        )}
                      />
                    </Field>

                    <div className='space-y-3'>
                      <div className='flex items-center justify-between gap-3'>
                        <button
                          type='button'
                          onClick={generatePassword}
                          className='flex items-center gap-1.5 text-xs font-medium hover:underline'
                          style={{ color: BRAND }}
                        >
                          <Sparkles className='h-3.5 w-3.5' />
                          {generation > 0 ? 'Generate another' : 'Generate strong password'}
                        </button>
                        {generation > 0 && data.password !== '' && (
                          <button
                            type='button'
                            onClick={copyPassword}
                            className='flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground'
                          >
                            {copied ? (
                              <Check className='h-3.5 w-3.5' />
                            ) : (
                              <Copy className='h-3.5 w-3.5' />
                            )}
                            {copied ? 'Copied' : 'Copy'}
                          </button>
                        )}
                      </div>
                      <PasswordStrength result={strength} />
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
                    {confirmMatches && (
                      <p className='!mt-1.5 flex items-center gap-1.5 text-xs text-green-600 dark:text-green-500'>
                        <Check className='h-3.5 w-3.5' /> Passwords match
                      </p>
                    )}
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
                    {step < LAST_STEP
                      ? 'Continue'
                      : processing
                        ? 'Creating account…'
                        : 'Create account'}
                  </button>
                </div>

                {step === LAST_STEP && (
                  <p className='text-center text-xs text-muted-foreground'>
                    Our team reviews every signup and emails you once you're approved.
                  </p>
                )}

                <p className='text-center text-sm text-muted-foreground'>
                  Already have an account?{' '}
                  <Link
                    href={route('login')}
                    className='font-medium hover:underline'
                    style={{ color: BRAND }}
                  >
                    Sign in
                  </Link>
                </p>
              </form>
            </div>
          </div>
        </div>
      </div>
    </ThemeProvider>
  )
}
