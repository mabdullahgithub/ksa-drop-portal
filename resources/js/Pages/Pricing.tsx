import { useEffect, useState } from 'react'
import { Head } from '@inertiajs/react'
import { ThinkingOrb } from 'thinking-orbs'
import {
  Moon, Sun, Truck, Warehouse, Wallet, MapPin, RotateCcw, Package,
  FileText, CreditCard, Clock, MessageCircle, Mail, ArrowRight,
  type LucideIcon,
} from 'lucide-react'

// ── Editable business details ──────────────────────────────────────────────────
// Everything a non-developer needs to change lives in this block.

const COMPANY = {
  name:      'KSA Drop',
  legalName: 'KSA DROP',
  email:     'support@ksadrop.com',
  location:  "Hayer Road, Al-Masani', Riyadh 14711, Saudi Arabia",
  // Primary number first — it is the one every CTA on the page dials.
  phones: [
    { display: '+966 55 510 0752', href: 'https://wa.me/966555100752' },
    { display: '+966 50 300 7729', href: 'https://wa.me/966503007729' },
  ],
  // Accounts are opened by our team over WhatsApp — there is no self-serve signup form.
  whatsappSignupHref:
    'https://wa.me/966555100752?text=Hi%20KSA%20Drop%2C%20I%27d%20like%20to%20open%20a%20merchant%20account%20for%20my%20Shopify%20store.',
  responseTime: 'within one business day',
}

const LAST_UPDATED = 'August 2026'

/**
 * Decorative background orb. thinking-orbs is strictly monochrome — it draws a
 * single grey channel with no colour of its own — so the only tuning needed is
 * opacity, which drops the near-black light-theme ink to a soft grey.
 *
 * 64 is the largest size the library ships (its two sizes are separate designs,
 * not a scale factor), so the backdrop is CSS-scaled up from there. The upscale
 * softens the dots, which at this opacity reads as an intended watermark rather
 * than a defect.
 */
const BACKDROP_SPEED = 0.4

const SERVICES: { icon: LucideIcon; title: string; body: string }[] = [
  {
    icon: Truck,
    title: 'Multi-courier delivery',
    body: 'Shipments are routed across J&T Express, iMile, and Navix, with the best available service selected per destination.',
  },
  {
    icon: Warehouse,
    title: 'Warehousing and fulfillment',
    body: 'Store inventory in our Saudi facilities. We pick, pack, and dispatch each order as it arrives from your store.',
  },
  {
    icon: Wallet,
    title: 'Cash on delivery',
    body: 'Cash is collected from your customer at the door, reconciled per shipment, and remitted to your bank account.',
  },
  {
    icon: MapPin,
    title: 'End-to-end tracking',
    body: 'Every shipment is tracked from pickup to delivery, with status updates written back to your Shopify order.',
  },
]

// What a merchant can be charged for. Amounts are deliberately not published —
// each merchant is quoted individually by the sales team.
const CHARGEABLE: { icon: LucideIcon; title: string; body: string }[] = [
  {
    icon: Truck,
    title: 'Delivery',
    body: 'Per shipment delivered, based on destination and weight. Remote destinations may carry a surcharge.',
  },
  {
    icon: Wallet,
    title: 'Cash-on-delivery collection',
    body: 'A collection fee applies to orders where cash is collected from the customer on your behalf.',
  },
  {
    icon: RotateCcw,
    title: 'Returns and re-attempts',
    body: 'Repeat delivery attempts and shipments returned to origin after a failed delivery.',
  },
  {
    icon: Package,
    title: 'Pick and pack',
    body: 'Per order picked, packed, and handed to the courier from our warehouse.',
  },
  {
    icon: Warehouse,
    title: 'Storage',
    body: 'Per unit, per month, for inventory held in our facilities beyond the included free period.',
  },
  {
    icon: FileText,
    title: 'Packaging materials',
    body: 'Charged only where packaging is supplied by us rather than by you.',
  },
]

const BILLING_STEPS: { icon: LucideIcon; title: string; body: string }[] = [
  {
    icon: Package,
    title: 'You ship',
    body: 'Service fees accrue per shipment as orders are picked up, delivered, returned, or stored.',
  },
  {
    icon: FileText,
    title: 'We invoice',
    body: 'A consolidated invoice covering the period is issued to you and made available in your portal account.',
  },
  {
    icon: CreditCard,
    title: 'You settle',
    body: 'Pay by bank transfer, Mada, or by offsetting the amount against cash-on-delivery funds we hold for you.',
  },
  {
    icon: Clock,
    title: 'Receipt issued',
    body: 'A VAT-compliant receipt is issued once payment clears and the invoice is marked paid in your account.',
  },
]

const POLICIES: { title: string; body: string }[] = [
  {
    title: 'Cancelling the service',
    body: 'There is no contract term and no cancellation fee. Uninstall the app or stop creating shipments at any time — no further fees accrue from that point.',
  },
  {
    title: 'Shipments already in transit',
    body: 'Shipments already picked up are completed and billed normally. A shipment cancelled before pickup is not billed at all.',
  },
  {
    title: 'Incorrect charges',
    body: 'Raise a dispute within 14 days of the invoice date. Charges found to be incorrect are credited against your next invoice, or refunded to your bank account if no further invoice is expected.',
  },
  {
    title: 'Funds held on your behalf',
    body: 'Any cash collected for you is remitted in the next payout cycle after you stop shipping, less any invoice still outstanding.',
  },
]

const ONBOARDING_STEPS: { title: string; body: string }[] = [
  {
    title: 'Message us on WhatsApp',
    body: 'Send your store name, the cities you ship to, and your rough monthly order volume. There is no online signup form — accounts are opened by our team.',
  },
  {
    title: 'We quote your rates',
    body: 'We review your volume and destinations and send you a written rate card covering every fee that will apply to your account.',
  },
  {
    title: 'Your account is created',
    body: 'Once you accept the rates we create your portal account and email your login details. You then install the free app and connect your store.',
  },
  {
    title: 'You start shipping',
    body: 'Orders sync automatically and fees begin only once your first shipment is picked up. Nothing is charged before that.',
  },
]

const FAQS: { q: string; a: string }[] = [
  {
    q: 'Will these charges appear on my Shopify bill?',
    a: 'No. Fulfillment and shipping fees are billed directly by KSA Drop and are settled outside of Shopify. They will not appear on your Shopify invoice, and Shopify is not involved in collecting, refunding, or disputing them.',
  },
  {
    q: 'Does the KSADrop Portal app itself cost anything?',
    a: 'No. Installing and using the app is free, with no subscription, per-seat, or per-order software fee. You pay only for physical logistics services you actually use.',
  },
  {
    q: 'Why are rates not published on this page?',
    a: 'Rates depend on your destinations, shipment weights, and monthly volume, so they are quoted per merchant rather than published as a single list. You receive a complete written rate card covering every applicable fee before your account is opened, and nothing is charged until you accept it.',
  },
  {
    q: 'When am I first charged?',
    a: 'Not until your first shipment is picked up. Opening an account, installing the app, and connecting your store are all free, and no payment details are taken up front.',
  },
  {
    q: 'What happens if I uninstall the app?',
    a: 'No further service fees accrue once you stop shipping with us. Any invoice already issued for work already performed remains payable, and any funds we hold on your behalf are remitted in the next payout cycle.',
  },
  {
    q: 'Is VAT included?',
    a: 'All quoted rates exclude VAT. 15% Saudi VAT is added to every invoice and shown as a separate line.',
  },
  {
    q: 'How do I dispute a charge?',
    a: 'Contact us within 14 days of the invoice date. Charges found to be incorrect are credited against your next invoice, or refunded to your bank account if no further invoice is expected.',
  },
]

// ── Page ───────────────────────────────────────────────────────────────────────

export default function Pricing() {
  // This page opens in light mode regardless of the visitor's saved portal theme.
  const [isDark, setIsDark] = useState(false)

  useEffect(() => {
    document.documentElement.classList.remove('dark')
  }, [])

  const toggleDark = () => {
    const next = !isDark
    setIsDark(next)
    document.documentElement.classList.toggle('dark', next)
  }

  return (
    <>
      <Head>
        <title>Pricing &amp; Billing — KSA Drop</title>
        <meta
          name="description"
          content="How KSA Drop merchants are charged for fulfillment, delivery, and cash-on-delivery services, and why those services are billed outside of Shopify."
        />
      </Head>

      <div className="relative min-h-screen bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">

        {/* ── Decorative background ──────────────────────────────── */}
        <div
          aria-hidden="true"
          className="pointer-events-none fixed inset-0 z-0 flex select-none items-center justify-center overflow-hidden"
        >
          <div className="scale-[4.5] opacity-[0.10] sm:scale-[6] lg:scale-[8] dark:opacity-[0.09]">
            <ThinkingOrb
              state="connecting"
              size={64}
              speed={BACKDROP_SPEED}
              theme={isDark ? 'dark' : 'light'}
            />
          </div>
        </div>

        {/* ── Header ─────────────────────────────────────────────── */}
        <header className="relative z-10 mx-auto flex max-w-3xl items-center justify-between px-6 pt-8">
          <img
            src={isDark ? '/images/email/logo-white.png' : '/images/email/logo.png'}
            alt="KSA Drop"
            className="h-7 w-auto"
          />
          <div className="flex items-center gap-5">
            <a
              href={COMPANY.whatsappSignupHref}
              target="_blank"
              rel="noopener noreferrer"
              className="text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
            >
              Contact sales
            </a>
            <button
              onClick={toggleDark}
              aria-label="Toggle dark mode"
              className="text-slate-400 transition-colors hover:text-slate-700 dark:hover:text-slate-200"
            >
              {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>
          </div>
        </header>

        <main className="relative z-10 mx-auto max-w-3xl px-6">

          {/* ── Hero ─────────────────────────────────────────────── */}
          <section className="pb-16 pt-20 sm:pt-24">
            <p className="text-sm font-medium text-orange-600 dark:text-orange-400">
              Merchant pricing &amp; billing
            </p>
            <h1 className="mt-4 text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl">
              The app is free.
              <br />
              You pay only for what you ship.
            </h1>
            <p className="mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-400">
              KSADrop Portal costs nothing to install or use. Fulfillment, delivery, and
              cash-on-delivery services are provided and billed separately by {COMPANY.name}, at
              rates quoted to you in writing before your account is opened.
            </p>
            <div className="mt-9 flex flex-wrap items-center gap-x-6 gap-y-3">
              <a
                href={COMPANY.whatsappSignupHref}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
              >
                <MessageCircle className="h-4 w-4" />
                Request a rate card
              </a>
              <a
                href={`mailto:${COMPANY.email}?subject=New%20merchant%20account%20request`}
                className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
              >
                Or email us
                <ArrowRight className="h-3.5 w-3.5" />
              </a>
            </div>
          </section>

          {/* ── Off-platform billing disclosure ───────────────────── */}
          <section className="py-8">
            <div className="rounded-2xl bg-slate-50 p-8 dark:bg-slate-900/60">
              <h2 className="text-lg font-semibold tracking-tight">
                Charges are billed outside of Shopify
              </h2>
              <p className="mt-4 leading-relaxed text-slate-600 dark:text-slate-400">
                The KSADrop Portal app is free. The fulfillment, delivery, and cash-on-delivery
                services described on this page are provided by {COMPANY.legalName} and are billed
                directly to you — not through the Shopify Billing API, and not on your Shopify
                invoice.
              </p>
              <ul className="mt-6 space-y-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                {[
                  'These fees never appear on your Shopify bill or Shopify payout statement.',
                  'Shopify does not collect, refund, or arbitrate these charges.',
                  'You receive a written rate card covering every applicable fee before your account is opened.',
                  'Nothing is charged until your first shipment is picked up.',
                ].map(line => (
                  <li key={line} className="flex gap-3">
                    <span className="mt-2 h-1 w-1 shrink-0 rounded-full bg-orange-500" />
                    <span>{line}</span>
                  </li>
                ))}
              </ul>
            </div>
          </section>

          {/* ── What the service covers ───────────────────────────── */}
          <section className="py-16">
            <h2 className="text-2xl font-semibold tracking-tight">What the service covers</h2>
            <div className="mt-10 grid gap-x-10 gap-y-9 sm:grid-cols-2">
              {SERVICES.map(({ icon: Icon, title, body }) => (
                <div key={title}>
                  <Icon className="h-5 w-5 text-orange-500" strokeWidth={1.75} />
                  <h3 className="mt-4 font-medium">{title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{body}</p>
                </div>
              ))}
            </div>
          </section>

          {/* ── What you are charged for ──────────────────────────── */}
          <section className="py-16">
            <h2 className="text-2xl font-semibold tracking-tight">What you are charged for</h2>
            <p className="mt-4 max-w-xl leading-relaxed text-slate-600 dark:text-slate-400">
              Rates depend on your destinations, shipment weights, and monthly volume, so they are
              quoted per merchant rather than published as a single list. Every fee that can apply to
              your account is set out below, and your written rate card prices each one before you
              commit to anything.
            </p>

            <div className="mt-10 grid gap-3 sm:grid-cols-2">
              {CHARGEABLE.map(({ icon: Icon, title, body }) => (
                <div key={title} className="rounded-2xl bg-slate-50 p-6 dark:bg-slate-900/60">
                  <div className="flex items-start justify-between gap-4">
                    <Icon className="h-5 w-5 text-orange-500" strokeWidth={1.75} />
                    <span className="whitespace-nowrap text-xs font-medium text-slate-400 dark:text-slate-500">
                      Quoted per merchant
                    </span>
                  </div>
                  <h3 className="mt-4 font-medium">{title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{body}</p>
                </div>
              ))}
            </div>

            <p className="mt-8 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
              There is no setup fee, no monthly minimum, and no charge for installing the app or
              connecting your store. All quoted rates exclude 15% VAT, which is shown as a separate
              line on every invoice.
            </p>

            <a
              href={COMPANY.whatsappSignupHref}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-6 inline-flex items-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
            >
              <MessageCircle className="h-4 w-4" />
              Request your rate card
            </a>
          </section>

          {/* ── How billing works ─────────────────────────────────── */}
          <section className="py-16">
            <h2 className="text-2xl font-semibold tracking-tight">How billing works</h2>
            <p className="mt-4 max-w-xl leading-relaxed text-slate-600 dark:text-slate-400">
              You are never charged up front, and never charged automatically for something you have
              not used. Invoicing follows the shipments you actually send.
            </p>

            <ol className="mt-10 space-y-8">
              {BILLING_STEPS.map(({ icon: Icon, title, body }, i) => (
                <li key={title} className="flex gap-5">
                  <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-50 dark:bg-slate-900/60">
                    <Icon className="h-4 w-4 text-orange-500" strokeWidth={1.75} />
                  </div>
                  <div className="pt-1">
                    <h3 className="font-medium">
                      <span className="mr-2 text-slate-400 dark:text-slate-500">{i + 1}</span>
                      {title}
                    </h3>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">{body}</p>
                  </div>
                </li>
              ))}
            </ol>

            <div className="mt-12 grid gap-x-10 gap-y-7 sm:grid-cols-2">
              {[
                { title: 'Accepted payment methods', body: 'Bank transfer in SAR, Mada, or settlement against cash-on-delivery funds held on your behalf.' },
                { title: 'Where to find your invoices', body: 'Every invoice is available in the Finance section of your KSA Drop portal account and emailed to your billing contact.' },
              ].map(({ title, body }) => (
                <div key={title}>
                  <h3 className="text-sm font-medium">{title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{body}</p>
                </div>
              ))}
            </div>
          </section>

          {/* ── Cancellation and refunds ──────────────────────────── */}
          <section className="py-16">
            <h2 className="text-2xl font-semibold tracking-tight">Cancellation and refunds</h2>
            <div className="mt-10 grid gap-x-10 gap-y-9 sm:grid-cols-2">
              {POLICIES.map(({ title, body }) => (
                <div key={title}>
                  <h3 className="font-medium">{title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{body}</p>
                </div>
              ))}
            </div>
          </section>

          {/* ── Opening an account ────────────────────────────────── */}
          <section className="py-16">
            <h2 className="text-2xl font-semibold tracking-tight">How to open an account</h2>
            <p className="mt-4 max-w-xl leading-relaxed text-slate-600 dark:text-slate-400">
              {COMPANY.name} accounts are opened by our sales team rather than through an online
              signup form. Message us and we will set you up {COMPANY.responseTime}.
            </p>

            <ol className="mt-10 space-y-8">
              {ONBOARDING_STEPS.map(({ title, body }, i) => (
                <li key={title} className="flex gap-5">
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-50 text-sm font-medium text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                    {i + 1}
                  </span>
                  <div className="pt-1">
                    <h3 className="font-medium">{title}</h3>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">{body}</p>
                  </div>
                </li>
              ))}
            </ol>
          </section>

          {/* ── FAQ ──────────────────────────────────────────────── */}
          <section className="py-16">
            <h2 className="text-2xl font-semibold tracking-tight">Common questions</h2>
            <div className="mt-8 space-y-2">
              {FAQS.map(({ q, a }) => (
                <details key={q} className="group rounded-2xl px-5 py-4 transition-colors open:bg-slate-50 hover:bg-slate-50 dark:open:bg-slate-900/60 dark:hover:bg-slate-900/60">
                  <summary className="flex cursor-pointer list-none items-center justify-between gap-6 text-[15px] font-medium marker:content-['']">
                    {q}
                    <span className="shrink-0 text-lg font-normal leading-none text-slate-400 transition-transform group-open:rotate-45">
                      +
                    </span>
                  </summary>
                  <p className="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">{a}</p>
                </details>
              ))}
            </div>
          </section>

          {/* ── Contact ──────────────────────────────────────────── */}
          <section className="py-16">
            <div className="rounded-2xl bg-slate-50 p-8 sm:p-10 dark:bg-slate-900/60">
              <h2 className="text-2xl font-semibold tracking-tight">Talk to our sales team</h2>
              <p className="mt-4 max-w-xl leading-relaxed text-slate-600 dark:text-slate-400">
                Tell us what you ship and where, and we will send you a full rate card. Opening an
                account is free and there is nothing to pay until your first shipment.
              </p>

              <div className="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
                <a
                  href={COMPANY.whatsappSignupHref}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                >
                  <MessageCircle className="h-4 w-4" />
                  Message us on WhatsApp
                </a>
                <a
                  href={`mailto:${COMPANY.email}?subject=New%20merchant%20account%20request`}
                  className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                >
                  Or email us
                  <ArrowRight className="h-3.5 w-3.5" />
                </a>
              </div>

              <div className="mt-9 flex flex-wrap gap-x-8 gap-y-3 text-sm text-slate-500 dark:text-slate-400">
                <a href={`mailto:${COMPANY.email}`} className="inline-flex items-center gap-2 transition-colors hover:text-slate-900 dark:hover:text-slate-100">
                  <Mail className="h-4 w-4" strokeWidth={1.75} />
                  {COMPANY.email}
                </a>
                {COMPANY.phones.map(({ display, href }) => (
                  <a key={href} href={href} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 transition-colors hover:text-slate-900 dark:hover:text-slate-100">
                    <MessageCircle className="h-4 w-4" strokeWidth={1.75} />
                    {display}
                  </a>
                ))}
                <span className="inline-flex items-center gap-2">
                  <MapPin className="h-4 w-4" strokeWidth={1.75} />
                  {COMPANY.location}
                </span>
              </div>
            </div>
          </section>

          {/* ── Footer ───────────────────────────────────────────── */}
          <footer className="flex flex-col gap-2 py-12 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between dark:text-slate-500">
            <p>© {new Date().getFullYear()} {COMPANY.legalName}</p>
            <p>Last updated {LAST_UPDATED} · All rates exclude 15% VAT</p>
          </footer>
        </main>
      </div>
    </>
  )
}
