import { useForm, usePage } from '@inertiajs/react'
import { ArrowRight, CheckCircle2, MessageCircle, Truck, Warehouse, Wallet, MapPin } from 'lucide-react'
import { PublicShell, btnPrimary, btnSecondary, fieldClass, labelClass, FieldError } from '@/features/partners/public-shell'
import type { PageProps } from '@/types'

type Props = {
  partner: { code: string; display_name: string; type: string; logo_url: string | null }
  whatsapp: string
}

const SERVICES = [
  { icon: Truck, title: 'Multi-courier delivery', body: 'J&T, iMile, Navix and our own riders, chosen per destination.' },
  { icon: Warehouse, title: 'Warehousing & pick-pack', body: 'Stock stays in our Riyadh facilities; we pack as orders arrive from Shopify.' },
  { icon: Wallet, title: 'Cash on delivery', body: 'Collected at the door, reconciled per shipment, remitted to your bank.' },
  { icon: MapPin, title: 'Tracking written back', body: 'Every status update lands on the Shopify order automatically.' },
]

const VOLUMES = ['Under 50', '50 – 200', '200 – 1,000', '1,000 – 5,000', 'Over 5,000']

export default function Referral({ partner, whatsapp }: Props) {
  const { flash } = usePage<PageProps>().props
  const { data, setData, post, processing, errors, wasSuccessful } = useForm({
    name: '',
    company: '',
    phone: '',
    email: '',
    store_url: '',
    monthly_orders: '',
    message: '',
    website_url_confirm: '',
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post(`/r/${partner.code}/lead`, { preserveScroll: true })
  }

  const done = wasSuccessful || !!flash?.success

  return (
    <PublicShell
      title={`${partner.display_name} recommends KSA Drop`}
      description='Open a KSA Drop fulfillment account for your Shopify store through a partner referral.'
    >
      <section className='pb-10 pt-16 sm:pt-20'>
        <div className='flex items-center gap-3'>
          {partner.logo_url ? (
            <img src={partner.logo_url} alt={partner.display_name} className='h-12 w-12 rounded-full object-cover ring-1 ring-slate-200 dark:ring-slate-800' />
          ) : (
            <span className='flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 text-lg font-semibold text-orange-700 dark:bg-orange-500/15 dark:text-orange-300'>
              {partner.display_name.slice(0, 1).toUpperCase()}
            </span>
          )}
          <div>
            <p className='text-sm font-medium text-orange-600 dark:text-orange-400'>Recommended by {partner.display_name}</p>
            <p className='text-xs text-slate-500 dark:text-slate-400'>Referral code <span className='font-mono font-semibold'>{partner.code}</span></p>
          </div>
        </div>
        <h1 className='mt-6 text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl'>
          Fulfillment for your Shopify store,
          <br />
          handled in Saudi Arabia.
        </h1>
        <p className='mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-400'>
          KSA Drop stores, packs, delivers and collects cash on delivery for Shopify merchants. The app is free;
          you pay only for what you ship. Leave your details and our team will open your account over WhatsApp.
        </p>
        <div className='mt-8 flex flex-wrap gap-3'>
          <a href={whatsapp} target='_blank' rel='noopener noreferrer' className={btnPrimary}>
            <MessageCircle className='h-4 w-4' /> Chat on WhatsApp
          </a>
          <a href='#signup' className={btnSecondary}>
            Leave my details <ArrowRight className='h-4 w-4' />
          </a>
        </div>
      </section>

      <section className='py-8'>
        <div className='grid gap-4 sm:grid-cols-2'>
          {SERVICES.map(({ icon: Icon, title, body }) => (
            <div key={title} className='flex gap-4 rounded-2xl bg-slate-50 p-5 dark:bg-slate-900/60'>
              <Icon className='mt-0.5 h-5 w-5 shrink-0 text-orange-600 dark:text-orange-400' />
              <div>
                <h3 className='font-semibold'>{title}</h3>
                <p className='mt-1 text-sm text-slate-600 dark:text-slate-400'>{body}</p>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section id='signup' className='py-10'>
        <div className='rounded-2xl border border-slate-200 p-6 sm:p-8 dark:border-slate-800'>
          {done ? (
            <div className='flex flex-col items-center py-8 text-center'>
              <CheckCircle2 className='h-12 w-12 text-emerald-600' />
              <h2 className='mt-4 text-xl font-semibold'>Thanks, we have your details</h2>
              <p className='mt-2 max-w-md text-sm text-slate-600 dark:text-slate-400'>
                Our team will message you on WhatsApp within one business day with a rate card and to open your account.
                Your referral by {partner.display_name} is recorded.
              </p>
              <a href={whatsapp} target='_blank' rel='noopener noreferrer' className={btnSecondary + ' mt-6'}>
                <MessageCircle className='h-4 w-4' /> Message us now instead
              </a>
            </div>
          ) : (
            <form onSubmit={submit} className='space-y-5'>
              <div>
                <h2 className='text-xl font-semibold'>Open a merchant account</h2>
                <p className='mt-1 text-sm text-slate-600 dark:text-slate-400'>
                  Nothing is charged until your first shipment is picked up. We quote your rates in writing first.
                </p>
              </div>

              <input
                type='text'
                name='website_url_confirm'
                value={data.website_url_confirm}
                onChange={(e) => setData('website_url_confirm', e.target.value)}
                className='hidden'
                tabIndex={-1}
                autoComplete='off'
                aria-hidden='true'
              />

              <div className='grid gap-4 sm:grid-cols-2'>
                <div>
                  <label className={labelClass} htmlFor='lead-name'>Your name *</label>
                  <input id='lead-name' className={fieldClass} value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                  <FieldError message={errors.name} />
                </div>
                <div>
                  <label className={labelClass} htmlFor='lead-company'>Store or company name</label>
                  <input id='lead-company' className={fieldClass} value={data.company} onChange={(e) => setData('company', e.target.value)} />
                  <FieldError message={errors.company} />
                </div>
                <div>
                  <label className={labelClass} htmlFor='lead-phone'>WhatsApp number *</label>
                  <input id='lead-phone' className={fieldClass} placeholder='+966 5x xxx xxxx' value={data.phone} onChange={(e) => setData('phone', e.target.value)} required />
                  <FieldError message={errors.phone} />
                </div>
                <div>
                  <label className={labelClass} htmlFor='lead-email'>Email</label>
                  <input id='lead-email' type='email' className={fieldClass} value={data.email} onChange={(e) => setData('email', e.target.value)} />
                  <FieldError message={errors.email} />
                </div>
                <div>
                  <label className={labelClass} htmlFor='lead-store'>Store URL</label>
                  <input id='lead-store' className={fieldClass} placeholder='mystore.myshopify.com' value={data.store_url} onChange={(e) => setData('store_url', e.target.value)} />
                  <FieldError message={errors.store_url} />
                </div>
                <div>
                  <label className={labelClass} htmlFor='lead-volume'>Orders per month</label>
                  <select id='lead-volume' className={fieldClass} value={data.monthly_orders} onChange={(e) => setData('monthly_orders', e.target.value)}>
                    <option value=''>Select…</option>
                    {VOLUMES.map((v) => <option key={v} value={v}>{v}</option>)}
                  </select>
                  <FieldError message={errors.monthly_orders} />
                </div>
              </div>
              <div>
                <label className={labelClass} htmlFor='lead-message'>Anything else?</label>
                <textarea id='lead-message' rows={3} className={fieldClass} placeholder='Cities you ship to, products, questions…' value={data.message} onChange={(e) => setData('message', e.target.value)} />
                <FieldError message={errors.message} />
              </div>
              <button type='submit' disabled={processing} className={btnPrimary}>
                {processing ? 'Sending…' : 'Send my details'} <ArrowRight className='h-4 w-4' />
              </button>
            </form>
          )}
        </div>
      </section>
    </PublicShell>
  )
}
