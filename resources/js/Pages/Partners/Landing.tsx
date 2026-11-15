import { useEffect, useState } from 'react'
import { Link } from '@inertiajs/react'
import { motion, useReducedMotion } from 'framer-motion'
import { ArrowRight } from 'lucide-react'
import {
  Storefront, Handshake, Megaphone, LinkSimple, Package, Money, SealCheck, Headset, ChartLineUp, RocketLaunch, ShieldCheck,
  type Icon as PhosphorIcon,
} from '@phosphor-icons/react'
import SwarmCursor from '@/components/swarm-cursor/SwarmCursor'
import { Illustration, Reveal, type IllustrationName } from '@/features/partners/animated-icon'
import { PublicShell, btnPrimary, btnSecondary } from '@/features/partners/public-shell'
import { ProgramTable, TierTable } from '@/features/partners/program-table'
import type { ProgramSummary } from '@/features/partners/types'
import { formatPoints } from '@/features/partners/types'

const PARTNER_TYPES: { art: IllustrationName; icon: PhosphorIcon; title: string; who: string; body: string; type: string }[] = [
  {
    art: 'seller',
    icon: Storefront,
    title: 'Sellers',
    who: 'Already ship with KSA Drop?',
    body: 'Refer other store owners you know. Switch partner mode on from your portal in one minute; no new account needed.',
    type: 'client',
  },
  {
    art: 'agency',
    icon: Handshake,
    title: 'Agencies & freelancers',
    who: 'You build or run Shopify stores',
    body: 'Hand your clients a fulfillment partner that works out of the box, and earn on every order they ship, for a year.',
    type: 'agency',
  },
  {
    art: 'influencer',
    icon: Megaphone,
    title: 'Influencers & communities',
    who: 'You teach or talk about e-commerce',
    body: 'Share your code with your audience, your course or your group. Each seller who joins is credited to you.',
    type: 'influencer',
  },
]

const PERKS: { icon: PhosphorIcon; title: string; body: string }[] = [
  { icon: LinkSimple, title: 'Your own link and code', body: 'A short referral link and a code sellers can type into WhatsApp. Both land on a page with your name on it.' },
  { icon: Package, title: 'Points on every delivery', body: 'Points for each shipment your referred sellers get delivered, for twelve months, with a tier multiplier as you grow.' },
  { icon: Money, title: 'Redeem for cash or more', body: 'Request a payout from your dashboard once you cross the minimum. We transfer by bank or the method you choose.' },
  { icon: SealCheck, title: 'Partner badge & listing', body: 'A badge for your site or profile, and a place in our partner directory for agencies.' },
  { icon: Headset, title: 'Priority support', body: 'A direct WhatsApp line to our team for anything your referred sellers need during onboarding.' },
  { icon: ChartLineUp, title: 'Transparent tracking', body: 'See every referred seller, their status and every point you earned, as it happens.' },
]

const STEPS: { icon: PhosphorIcon; title: string; body: string }[] = [
  { icon: ShieldCheck, title: 'Apply', body: 'Tell us who you are and how you plan to promote KSA Drop. Upload your ID so we know every partner is real. We review within two business days.' },
  { icon: RocketLaunch, title: 'Share your link', body: 'Once approved, your referral link and code go live. Share them in your content, with your clients, or in your community.' },
  { icon: Money, title: 'Earn as they ship', body: 'We open the seller\'s account over WhatsApp and credit them to you. Points arrive as their shipments are delivered.' },
]

const HERO_CHIPS = (program: ProgramSummary): { icon: PhosphorIcon; label: string; note: string; pos: string }[] => [
  { icon: LinkSimple, label: 'Your link & code', note: 'Every seller credited to you', pos: 'left-0 top-[16%]' },
  { icon: Package, label: `+${program.delivery_points} pt per delivery`, note: 'Multiplied as your tier grows', pos: 'right-0 top-[16%]' },
  { icon: SealCheck, label: `${program.delivery_months} months`, note: 'Of earnings on every seller', pos: 'left-0 bottom-[16%]' },
  { icon: Money, label: 'Cash payouts', note: `From ${formatPoints(program.min_redeem_points)} points`, pos: 'right-0 bottom-[16%]' },
]

const FAQS = [
  { q: 'What are points worth?', a: 'Points are our internal currency. One point is valued at one Saudi riyal when we pay out. Redemption is by request and handled by our team, by bank transfer or another method you add to your profile. We may add more ways to use points over time.' },
  { q: 'How do you know a seller came from me?', a: 'Three ways: they open your link, which shows a sign-up form that goes straight to our team with your code; they mention your code on WhatsApp; or you tell us the seller\'s name and we match it. Our team attaches you to the account when it is created.' },
  { q: 'What does the referred seller get?', a: 'The same quoted rates as any merchant, onboarding by our team, and any promotion we run for your audience. We can set up a dedicated offer for partners with large audiences.' },
  { q: 'Can I refer my own store?', a: 'No. Self-referrals, duplicate accounts and sellers already working with us are not credited.' },
  { q: 'Is there a minimum to get paid?', a: 'Yes, and it is shown on this page under Points. Payouts are requested from your dashboard and processed monthly.' },
  { q: 'How long do I earn on a seller?', a: 'Per-shipment points run for twelve months from the seller\'s first delivered order. Activation and milestone bonuses are one-time.' },
]

/** Tracks the `dark` class that the shell toggles on <html>. */
function useIsDark() {
  const [dark, setDark] = useState(true)
  useEffect(() => {
    const el = document.documentElement
    const sync = () => setDark(el.classList.contains('dark'))
    sync()
    const mo = new MutationObserver(sync)
    mo.observe(el, { attributes: true, attributeFilter: ['class'] })
    return () => mo.disconnect()
  }, [])
  return dark
}

export default function Landing({ program }: { program: ProgramSummary }) {
  const reduce = useReducedMotion()
  const dark = useIsDark()
  return (
    <PublicShell
      wide
      title='Partner Program'
      description='Refer Shopify sellers to KSA Drop fulfillment and earn points on every delivery for a year. For sellers, agencies, influencers and communities.'
    >
      {/* Hero */}
      <section className='relative left-1/2 mb-6 mt-4 w-screen -translate-x-1/2'>
        <SwarmCursor
          className='h-[30rem] sm:h-[40rem]'
          count={1500}
          size={3.4}
          spread={200}
          speed={0.8}
          swirl={0.7}
          tail={0.6}
          depth={0.85}
          idle='gather'
          color={dark ? '#fdba74' : '#ea580c'}
          accentColor={dark ? '#ffffff' : '#9a3412'}
          clickEffect='burst'
          lightMode={!dark}
          targets=''
        >
          <div className='flex h-full flex-col items-center justify-center px-6 text-center'>
        <h1 className='sr-only'>KSA Drop partner program: bring sellers, earn on every delivery</h1>

        <div className='relative mx-auto flex h-[22rem] w-full max-w-3xl items-center justify-center sm:h-[30rem]'>
          {/* Orbit rings */}
          <div aria-hidden='true' className='pointer-events-none absolute left-1/2 top-1/2 aspect-square w-[22rem] -translate-x-1/2 -translate-y-1/2 sm:w-[27rem]'>
            <div className='absolute inset-[12%] rounded-full bg-orange-200/40 blur-3xl dark:bg-orange-500/10' />
            <svg viewBox='0 0 200 200' className='absolute inset-0 h-full w-full'>
              <circle cx='100' cy='100' r='99' fill='none' strokeWidth='0.6' className='stroke-slate-200 dark:stroke-slate-800' />
              <circle cx='100' cy='100' r='82' fill='none' strokeWidth='0.6' className='stroke-slate-200 dark:stroke-slate-800' />
            </svg>
            <motion.svg
              viewBox='0 0 200 200'
              className='absolute inset-0 h-full w-full'
              animate={reduce ? undefined : { rotate: 360 }}
              transition={{ duration: 36, repeat: Infinity, ease: 'linear' }}
            >
              <circle cx='100' cy='100' r='90.5' fill='none' strokeWidth='0.8' strokeDasharray='1 5' strokeLinecap='round' className='stroke-orange-400/80' />
              <circle cx='100' cy='9.5' r='3' className='fill-orange-500' />
              <circle cx='100' cy='190.5' r='2' className='fill-orange-400/70' />
            </motion.svg>
            <motion.svg
              viewBox='0 0 200 200'
              className='absolute inset-0 h-full w-full'
              animate={reduce ? undefined : { rotate: -360 }}
              transition={{ duration: 52, repeat: Infinity, ease: 'linear' }}
            >
              <circle cx='18' cy='100' r='2.5' className='fill-slate-400 dark:fill-slate-500' />
            </motion.svg>
          </div>

          <Illustration name='hero' eager className='relative w-[16rem] sm:w-[21rem]' />

          {HERO_CHIPS(program).map((chip, i) => (
            <motion.div
              key={chip.label}
              aria-hidden='true'
              className={`absolute ${chip.pos} hidden w-56 items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-left shadow-sm sm:flex dark:border-slate-800 dark:bg-slate-900`}
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.25 + i * 0.12, ease: 'easeOut' }}
            >
              <span className='flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400'>
                <chip.icon weight='duotone' className='h-5 w-5' />
              </span>
              <span>
                <span className='block text-sm font-semibold leading-tight'>{chip.label}</span>
                <span className='mt-0.5 block text-xs text-slate-500 dark:text-slate-400'>{chip.note}</span>
              </span>
            </motion.div>
          ))}
        </div>
          </div>
        </SwarmCursor>
      </section>

      {/* Journey strip: link -> delivery -> cash */}
      <section className='pb-6'>
        <ul className='grid gap-3 sm:grid-cols-3'>
          {[
            { icon: LinkSimple, label: 'Seller opens your link', note: 'Credited to you' },
            { icon: Package, label: 'Their orders get delivered', note: `+${program.delivery_points} pt each, ${program.delivery_months} months` },
            { icon: Money, label: 'You redeem points', note: `From ${formatPoints(program.min_redeem_points)} pts` },
          ].map((row, i) => (
            <Reveal key={row.label} delay={i * 0.08}>
              <li className='flex items-center gap-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-800'>
                <span className='flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400'><row.icon weight='duotone' className='h-6 w-6' /></span>
                <div>
                  <p className='text-sm font-semibold'>{row.label}</p>
                  <p className='text-xs text-slate-500 dark:text-slate-400'>{row.note}</p>
                </div>
              </li>
            </Reveal>
          ))}
        </ul>
      </section>

      {/* Who */}
      <section className='py-10'>
        <h2 className='text-2xl font-semibold tracking-tight'>Built for three kinds of partner</h2>
        <div className='mt-8 grid gap-4 sm:grid-cols-3'>
          {PARTNER_TYPES.map(({ art, icon: Icon, title, who, body, type }, i) => (
            <Reveal key={title} delay={i * 0.08} className='flex'>
            <div className='flex w-full flex-col rounded-2xl bg-slate-50 p-6 transition-transform hover:-translate-y-1 dark:bg-slate-900/60'>
              <Illustration name={art} className='-mx-2 -mt-2 mb-2' />
              <div className='flex items-center gap-2'><Icon weight='duotone' className='h-7 w-7 text-orange-600 dark:text-orange-400' /></div>
              <h3 className='mt-3 text-lg font-semibold'>{title}</h3>
              <p className='text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400'>{who}</p>
              <p className='mt-3 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400'>{body}</p>
              <Link
                href={type === 'client' ? '/login' : `/partners/apply?type=${type}`}
                className='mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-slate-900 hover:underline dark:text-slate-100'
              >
                {type === 'client' ? 'Sign in and join' : `Apply as ${title.toLowerCase()}`}
                <ArrowRight className='h-3.5 w-3.5' />
              </Link>
            </div>
            </Reveal>
          ))}
        </div>
      </section>

      {/* Points */}
      <section className='py-10'>
        <div className='flex items-center gap-6'>
          <div className='flex-1'>
            <h2 className='text-2xl font-semibold tracking-tight'>Points: how you earn</h2>
            <p className='mt-3 max-w-xl text-slate-600 dark:text-slate-400'>
              One point is valued at one Saudi riyal at payout. Redeem from {formatPoints(program.min_redeem_points)} points.
            </p>
          </div>
          <Illustration name='payout' className='hidden w-40 shrink-0 sm:block' />
        </div>
        <div className='mt-8'>
          <ProgramTable program={program} />
        </div>
        <h3 className='mt-10 text-lg font-semibold'>Tiers multiply your per-delivery points</h3>
        <p className='mt-2 text-sm text-slate-600 dark:text-slate-400'>
          A seller counts as active once their first order is delivered. Your tier is recalculated automatically.
        </p>
        <div className='mt-5'>
          <TierTable program={program} />
        </div>
      </section>

      {/* Perks */}
      <section className='py-10'>
        <h2 className='text-2xl font-semibold tracking-tight'>What partners get</h2>
        <div className='mt-8 grid gap-6 sm:grid-cols-2'>
          {PERKS.map(({ icon: Icon, title, body }, i) => (
            <Reveal key={title} delay={(i % 2) * 0.08} className='flex gap-4 rounded-2xl border border-slate-200 p-5 dark:border-slate-800'>
              <div className='flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-500/10'>
                <Icon weight='duotone' className='h-6 w-6 text-orange-600 dark:text-orange-400' />
              </div>
              <div>
                <h3 className='font-semibold'>{title}</h3>
                <p className='mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400'>{body}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </section>

      {/* Steps */}
      <section className='py-10'>
        <h2 className='text-2xl font-semibold tracking-tight'>Start in three steps</h2>
        <ol className='mt-8 space-y-6'>
          {STEPS.map((step, i) => (
            <li key={step.title} className='flex gap-5'>
              <div className='relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-50 dark:bg-slate-900/60'>
                <step.icon weight='duotone' className='h-6 w-6 text-orange-600 dark:text-orange-400' />
                <span className='absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900'>
                  {i + 1}
                </span>
              </div>
              <div>
                <h3 className='font-semibold'>{step.title}</h3>
                <p className='mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400'>{step.body}</p>
              </div>
            </li>
          ))}
        </ol>
      </section>

      {/* FAQ */}
      <section className='py-10'>
        <h2 className='text-2xl font-semibold tracking-tight'>Questions</h2>
        <div className='mt-6 divide-y divide-slate-200 dark:divide-slate-800'>
          {FAQS.map((f) => (
            <details key={f.q} className='group py-4'>
              <summary className='flex cursor-pointer list-none items-center justify-between gap-4 font-medium'>
                {f.q}
                <span className='text-slate-400 transition-transform group-open:rotate-45'>+</span>
              </summary>
              <p className='mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400'>{f.a}</p>
            </details>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className='py-10'>
        <div className='relative overflow-hidden rounded-2xl bg-slate-900 p-8 text-white dark:bg-white dark:text-slate-900'>
          <Illustration name='cta' className='absolute -bottom-6 -right-2 hidden w-56 sm:block' />
          <h2 className='text-2xl font-semibold tracking-tight'>Ready to partner with KSA Drop?</h2>
          <p className='mt-2 max-w-lg text-sm leading-relaxed text-slate-300 dark:text-slate-600'>
            The application takes about five minutes. Have your ID and your social or website links ready.
          </p>
          <div className='mt-6 flex flex-wrap gap-3'>
            <Link href='/partners/apply' className='inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-medium text-slate-900 hover:bg-slate-200 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-700'>
              Apply now <ArrowRight className='h-4 w-4' />
            </Link>
            <a href='mailto:support@ksadrop.com?subject=Partner%20program%20question' className={btnSecondary + ' border-slate-600 text-slate-200 hover:border-white hover:text-white dark:border-slate-300 dark:text-slate-700'}>
              Ask a question
            </a>
          </div>
        </div>
      </section>
    </PublicShell>
  )
}
