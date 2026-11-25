import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEventHandler, ReactNode } from 'react'
import { Check, PackageOpen, Truck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PasswordInput } from '@/components/password-input'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'
import { AuthDivider, GoogleButton } from '@/components/auth/google-button'

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

type GoogleProfile = { name: string; email: string }

export default function Register({
  googleEnabled = false,
  google = null,
}: {
  googleEnabled?: boolean
  google?: GoogleProfile | null
}) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: google?.name ?? '',
    company_name: '',
    email: google?.email ?? '',
    phone: '',
    city: '',
    client_type: '' as ClientType | '',
    password: '',
    password_confirmation: '',
    website: '', // honeypot, hidden from people
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('register'), {
      onFinish: () => reset('password', 'password_confirmation'),
    })
  }

  const servicesError = errors.client_type

  const useAnotherMethod = () => router.post(route('auth.google.cancel'))

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
                <p className='mt-1.5 text-sm text-muted-foreground'>
                  {google
                    ? 'Almost there. Tell us about your business to finish signing up.'
                    : "Create your merchant account. Our team reviews every signup and emails you once you're approved."}
                </p>
              </div>

              {google ? (
                <div className='mb-6 flex items-center justify-between gap-3 rounded-lg border border-input bg-muted/40 px-3.5 py-3 text-sm'>
                  <div className='min-w-0'>
                    <p className='text-xs text-muted-foreground'>Signing up with Google as</p>
                    <p className='truncate font-medium'>{google.email}</p>
                  </div>
                  <button
                    type='button'
                    onClick={useAnotherMethod}
                    className='shrink-0 text-xs font-medium hover:underline'
                    style={{ color: BRAND }}
                  >
                    Change
                  </button>
                </div>
              ) : (
                googleEnabled && (
                  <div className='mb-6 space-y-6'>
                    <GoogleButton label='Sign up with Google' />
                    <AuthDivider />
                  </div>
                )
              )}

              <form onSubmit={submit} className='space-y-5' noValidate>
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

                {!google && (
                  <Field id='email' label='Email ID' required error={errors.email}>
                    <Input
                      id='email'
                      type='email'
                      value={data.email}
                      autoComplete='email'
                      onChange={(e) => setData('email', e.target.value)}
                      className={cn(inputClass, errors.email && 'border-destructive')}
                    />
                  </Field>
                )}
                {google && errors.email && (
                  <p className='text-sm text-destructive'>{errors.email}</p>
                )}

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

                {!google && (
                  <>
                    <Field id='password' label='Password' required error={errors.password}>
                      <PasswordInput
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

                    <Field
                      id='password_confirmation'
                      label='Confirm password'
                      required
                      error={errors.password_confirmation}
                    >
                      <PasswordInput
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

                <button
                  type='submit'
                  disabled={processing}
                  className='flex h-12 w-full items-center justify-center rounded-lg text-sm font-semibold text-white shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60'
                  style={{ backgroundColor: BRAND }}
                  onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = BRAND_HOVER)}
                  onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = BRAND)}
                >
                  {processing ? 'Creating account…' : 'Create account'}
                </button>

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
