import { Download } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { CopyButton, PartnerShell } from '@/features/partners/partner-shell'
import type { PartnerSummary } from '@/features/partners/types'

type Props = { partner: PartnerSummary }

const LOGOS = [
  { label: 'KSA Drop Partner logo (PNG, 1600px)', href: '/images/partners/ksa-drop-partner-logo.png' },
  { label: 'KSA Drop Partner logo (PDF, vector)', href: '/images/partners/ksa-drop-partner-logo.pdf' },
  { label: 'KSA Drop Partner badge (PNG, 600px)', href: '/images/partners/ksa-drop-partner-badge.png' },
  { label: 'KSA Drop logo (colour, PNG)', href: '/ksa-al-logo-files/Logo/ksa_drop_logo.png.png' },
  { label: 'KSA Drop logo (white, PNG)', href: '/ksa-al-logo-files/Logo/KSA Drop Logo PNG-03.png' },
]

/** Ready-to-paste copy and brand files. Every snippet carries the partner's link. */
export default function Assets({ partner }: Props) {
  const link = partner.referral_url
  const snippets = [
    {
      title: 'Short post (English)',
      text: `Running a Shopify store in Saudi Arabia? KSA Drop stores, packs, delivers and collects COD for you. The app is free, you pay only for what you ship. Open your account here: ${link}`,
    },
    {
      title: 'Short post (Arabic)',
      text: `عندك متجر شوبيفاي في السعودية؟ KSA Drop يتكفل بالتخزين والتغليف والتوصيل وتحصيل الدفع عند الاستلام. التطبيق مجاني وتدفع فقط على الشحنات. افتح حسابك من هنا: ${link}`,
    },
    {
      title: 'WhatsApp message to a seller',
      text: `Hi! I use KSA Drop for fulfillment and COD in Saudi. They quote your rates in writing before you start and nothing is charged until your first pickup. Mention my code ${partner.code} or sign up here: ${link}`,
    },
    {
      title: 'Agency client handover',
      text: `For fulfillment, delivery and cash-on-delivery across Saudi Arabia we recommend KSA Drop. Their Shopify app syncs orders automatically and writes tracking back to the order. Open your account with our partner link: ${link}`,
    },
  ]

  const badge = `<a href="${link}" rel="nofollow"><img src="${window.location.origin}/images/partners/ksa-drop-partner-badge.png" alt="KSA Drop Partner" height="40"></a>`

  return (
    <PartnerShell title='Marketing assets' description='Copy, logos and a badge, each already carrying your link.' partner={partner}>
      <div className='grid gap-6 lg:grid-cols-2'>
        <div className='space-y-4'>
          {snippets.map((s) => (
            <Card key={s.title}>
              <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                <CardTitle className='text-base'>{s.title}</CardTitle>
                <CopyButton value={s.text} />
              </CardHeader>
              <CardContent>
                <p className='whitespace-pre-wrap rounded-md bg-muted p-3 text-sm' dir='auto'>{s.text}</p>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className='space-y-4'>
          <Card>
            <CardHeader>
              <CardTitle className='text-base'>Logos</CardTitle>
              <CardDescription>Use the Partner mark on your site and profiles. Do not recolour, stretch or add effects.</CardDescription>
            </CardHeader>
            <CardContent className='space-y-2'>
              <div className='rounded-md bg-white p-4 ring-1 ring-border'>
                <img src='/images/partners/ksa-drop-partner-logo.png' alt='KSA Drop Partner' className='mx-auto h-16 w-auto' />
              </div>
              {LOGOS.map((l) => (
                <div key={l.href} className='flex items-center justify-between gap-3 rounded-md border p-3'>
                  <div className='flex items-center gap-3'>
                    {l.href.endsWith('.pdf') ? (
                      <span className='flex h-7 w-10 items-center justify-center rounded bg-slate-100 text-[10px] font-semibold text-slate-600'>PDF</span>
                    ) : (
                      <img src={l.href} alt='' className='h-7 w-auto rounded bg-white p-1 ring-1 ring-border' />
                    )}
                    <span className='text-sm'>{l.label}</span>
                  </div>
                  <Button asChild variant='outline' size='sm'>
                    <a href={l.href} download><Download className='h-4 w-4' /> Download</a>
                  </Button>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
              <div>
                <CardTitle className='text-base'>Partner badge for your website</CardTitle>
                <CardDescription>Paste this HTML anywhere on your site.</CardDescription>
              </div>
              <CopyButton value={badge} />
            </CardHeader>
            <CardContent>
              <pre className='overflow-x-auto rounded-md bg-muted p-3 text-xs'>{badge}</pre>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className='text-base'>House rules</CardTitle>
            </CardHeader>
            <CardContent className='space-y-2 text-sm text-muted-foreground'>
              <p>Say you are a partner, never that you are KSA Drop. Do not promise rates: every seller gets a written quote from our team.</p>
              <p>No paid ads on the KSA Drop brand name, no spam, no self-referrals. Breaking these ends the partnership and forfeits unpaid points.</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </PartnerShell>
  )
}
