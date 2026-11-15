import { useEffect, useState } from 'react'
import axios from 'axios'
import { ExternalLink, FileText } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Textarea } from '@/components/ui/textarea'
import { usePermissions } from '@/hooks/use-permissions'
import { StatusBadge, TierDot } from '@/features/partners/partner-shell'
import {
  PARTNER_TYPE_LABEL, SOCIAL_LABEL, TIER_LABEL, TRANSACTION_LABEL, formatDate, formatPoints,
} from '@/features/partners/types'
import type { AdminTransaction, PartnerDetail, PartnerRow } from './types'

type Props = {
  partnerId: number | null
  onOpenChange: (open: boolean) => void
  onChanged: (row: PartnerRow) => void
}

const errorMessage = (e: unknown) =>
  (axios.isAxiosError(e) && (e.response?.data?.message as string)) || 'Something went wrong.'

export function PartnerDetailSheet({ partnerId, onOpenChange, onChanged }: Props) {
  const { can } = usePermissions()
  const [partner, setPartner] = useState<PartnerDetail | null>(null)
  const [transactions, setTransactions] = useState<AdminTransaction[] | null>(null)
  const [busy, setBusy] = useState(false)
  const [reason, setReason] = useState('')
  const [adjust, setAdjust] = useState({ points: '', note: '' })
  const [edit, setEdit] = useState({ code: '', tier: 'bronze', admin_notes: '' })

  useEffect(() => {
    if (!partnerId) { setPartner(null); setTransactions(null); return }
    let cancelled = false
    axios.get(`/api/partners/${partnerId}`).then(({ data }) => {
      if (cancelled) return
      setPartner(data.partner)
      setEdit({ code: data.partner.code, tier: data.partner.tier, admin_notes: data.partner.admin_notes ?? '' })
      setReason('')
      setAdjust({ points: '', note: '' })
    }).catch((e) => toast.error(errorMessage(e)))
    axios.get(`/api/partners/${partnerId}/transactions`).then(({ data }) => !cancelled && setTransactions(data.transactions)).catch(() => {})
    return () => { cancelled = true }
  }, [partnerId])

  const act = async (fn: () => Promise<{ data: { partner: PartnerDetail; message?: string } }>) => {
    setBusy(true)
    try {
      const { data } = await fn()
      setPartner(data.partner)
      onChanged(data.partner)
      if (data.message) toast.success(data.message)
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  const p = partner

  return (
    <Sheet open={!!partnerId} onOpenChange={onOpenChange}>
      <SheetContent className='w-full overflow-y-auto sm:max-w-2xl'>
        {!p ? (
          <SheetHeader><SheetTitle>Loading…</SheetTitle><SheetDescription>Fetching the partner.</SheetDescription></SheetHeader>
        ) : (
          <div className='space-y-6 px-4 pb-8'>
            <SheetHeader className='px-0'>
              <SheetTitle className='flex flex-wrap items-center gap-2'>
                {p.display_name}
                <StatusBadge status={p.status} />
                <span className='inline-flex items-center gap-1.5 text-sm font-normal text-muted-foreground'><TierDot tier={p.tier} />{TIER_LABEL[p.tier]}</span>
              </SheetTitle>
              <SheetDescription>
                {PARTNER_TYPE_LABEL[p.type]} · code <span className='font-mono font-semibold'>{p.code}</span> · {formatPoints(p.points_balance)} points
                {p.own_client && <> · also client <a className='underline' href={`/client/${p.own_client.id}`}>{p.own_client.company_name}</a></>}
              </SheetDescription>
            </SheetHeader>

            {/* Decision */}
            {can('review partner applications') && (p.status === 'pending' || p.status === 'rejected') && (
              <section className='rounded-lg border border-amber-500/40 bg-amber-500/5 p-4'>
                <h3 className='font-semibold'>Decide on this application</h3>
                <p className='mt-1 text-sm text-muted-foreground'>Approving sends the welcome email, awards the welcome bonus and switches the referral link on.</p>
                <div className='mt-3 grid gap-2'>
                  <Textarea rows={2} placeholder='Reason (sent to the applicant when rejecting)' value={reason} onChange={(e) => setReason(e.target.value)} />
                  <div className='flex gap-2'>
                    <Button disabled={busy} onClick={() => act(() => axios.post(`/api/partners/${p.id}/approve`))}>Approve</Button>
                    {p.status === 'pending' && (
                      <Button variant='destructive' disabled={busy || reason.trim().length < 3} onClick={() => act(() => axios.post(`/api/partners/${p.id}/reject`, { reason }))}>Reject</Button>
                    )}
                  </div>
                </div>
              </section>
            )}

            {can('suspend partners') && (p.status === 'approved' || p.status === 'suspended') && (
              <div className='flex items-center justify-between rounded-lg border p-3 text-sm'>
                <span>{p.status === 'approved' ? 'Partner is active. Suspending pauses the link; points are kept.' : 'Partner is suspended.'}</span>
                <Button size='sm' variant={p.status === 'approved' ? 'destructive' : 'default'} disabled={busy} onClick={() => act(() => axios.post(`/api/partners/${p.id}/status`, { suspended: p.status === 'approved' }))}>
                  {p.status === 'approved' ? 'Suspend' : 'Reactivate'}
                </Button>
              </div>
            )}

            {/* Application */}
            <section>
              <h3 className='mb-2 font-semibold'>Application</h3>
              <dl className='grid grid-cols-[140px_1fr] gap-y-1.5 text-sm'>
                <Dt>Applicant</Dt><Dd>{p.user?.name} · {p.user?.email}</Dd>
                <Dt>Company / brand</Dt><Dd>{p.company_name ?? '—'}</Dd>
                <Dt>Phone</Dt><Dd>{p.phone}{p.whatsapp ? ` · WhatsApp ${p.whatsapp}` : ''}</Dd>
                <Dt>Location</Dt><Dd>{[p.city, p.country].filter(Boolean).join(', ')}</Dd>
                <Dt>Website</Dt><Dd>{p.website ? <a className='underline' href={p.website} target='_blank' rel='noreferrer'>{p.website} <ExternalLink className='inline h-3 w-3' /></a> : '—'}</Dd>
                <Dt>Audience</Dt><Dd>{p.audience_size ?? '—'}</Dd>
                {Object.entries(p.social_links ?? {}).filter(([, v]) => v).map(([k, v]) => (
                  <Fragmentish key={k} label={SOCIAL_LABEL[k as keyof typeof SOCIAL_LABEL] ?? k} value={v} />
                ))}
                <Dt>ID document</Dt>
                <Dd>
                  {p.has_id_document ? (
                    can('view partner documents') ? (
                      <span className='inline-flex items-center gap-3'>
                        {([['front', 'Front', true], ['back', 'Back', !!p.has_id_document_back]] as const).filter(([, , on]) => on).map(([side, label]) => (
                          <a key={side} className='inline-flex items-center gap-1 underline' href={`/api/partners/${p.id}/document?side=${side}`} target='_blank' rel='noreferrer'>
                            <FileText className='h-3.5 w-3.5' /> {label}{side === 'front' && p.id_document_type ? ` (${p.id_document_type.replace('_', ' ')})` : ''}
                          </a>
                        ))}
                      </span>
                    ) : 'On file'
                  ) : 'Not provided'}
                </Dd>
                <Dt>Terms</Dt><Dd>{p.terms_version} · {formatDate(p.terms_accepted_at)}</Dd>
                <Dt>Applied</Dt><Dd>{formatDate(p.created_at)}</Dd>
                {p.reviewed_at && <><Dt>Reviewed</Dt><Dd>{p.reviewed_by} · {formatDate(p.reviewed_at)}</Dd></>}
                {p.rejection_reason && <><Dt>Rejection reason</Dt><Dd>{p.rejection_reason}</Dd></>}
              </dl>
              <div className='mt-3 space-y-3'>
                <div>
                  <div className='text-xs font-medium uppercase tracking-wide text-muted-foreground'>How they will promote</div>
                  <p className='mt-1 whitespace-pre-wrap rounded-md bg-muted p-3 text-sm' dir='auto'>{p.promotion_plan ?? '—'}</p>
                </div>
                <div>
                  <div className='text-xs font-medium uppercase tracking-wide text-muted-foreground'>Motivation letter</div>
                  <p className='mt-1 whitespace-pre-wrap rounded-md bg-muted p-3 text-sm' dir='auto'>{p.motivation ?? '—'}</p>
                </div>
              </div>
            </section>

            <Separator />

            {/* Payout method */}
            <section>
              <h3 className='mb-2 font-semibold'>Payout method</h3>
              {p.payout_method?.type ? (
                <dl className='grid grid-cols-[140px_1fr] gap-y-1.5 text-sm'>
                  <Dt>Method</Dt><Dd className='capitalize'>{p.payout_method.type}</Dd>
                  {p.payout_method.type === 'bank' ? (
                    <>
                      <Dt>Bank</Dt><Dd>{p.payout_method.bank_name}</Dd>
                      <Dt>Holder</Dt><Dd>{p.payout_method.holder_name}</Dd>
                      <Dt>IBAN</Dt><Dd className='font-mono'>{p.payout_method.iban}</Dd>
                    </>
                  ) : (
                    <><Dt>Details</Dt><Dd>{p.payout_method.details}</Dd></>
                  )}
                </dl>
              ) : <p className='text-sm text-muted-foreground'>Not provided yet.</p>}
            </section>

            <Separator />

            {/* Referrals */}
            <section>
              <h3 className='mb-2 font-semibold'>Referred clients ({p.referred_clients.length})</h3>
              {p.referred_clients.length === 0 ? (
                <p className='text-sm text-muted-foreground'>None yet. Enter the code <span className='font-mono'>{p.code}</span> in "Referred by partner" when creating a client.</p>
              ) : (
                <ul className='divide-y rounded-md border text-sm'>
                  {p.referred_clients.map((c) => (
                    <li key={c.id} className='flex items-center justify-between gap-2 px-3 py-2'>
                      <a className='font-medium underline-offset-2 hover:underline' href={`/client/${c.id}`}>{c.company_name} <span className='font-mono text-xs text-muted-foreground'>{c.client_id}</span></a>
                      <span className='text-xs text-muted-foreground'>{c.activated_at ? `active since ${formatDate(c.activated_at)}` : `referred ${formatDate(c.referred_at)}`}</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {/* Edit */}
            {can('edit partners') && (
              <>
                <Separator />
                <section className='space-y-3'>
                  <h3 className='font-semibold'>Edit</h3>
                  <div className='grid gap-3 sm:grid-cols-2'>
                    <div className='grid gap-1.5'>
                      <Label>Referral code</Label>
                      <Input className='font-mono uppercase' value={edit.code} onChange={(e) => setEdit({ ...edit, code: e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '') })} />
                    </div>
                    <div className='grid gap-1.5'>
                      <Label>Tier override</Label>
                      <select className='h-9 rounded-md border border-input bg-transparent px-3 text-sm' value={edit.tier} onChange={(e) => setEdit({ ...edit, tier: e.target.value })}>
                        <option value='bronze'>Bronze</option><option value='silver'>Silver</option><option value='gold'>Gold</option>
                      </select>
                      <p className='text-xs text-muted-foreground'>Recomputed from active referrals at the next award.</p>
                    </div>
                  </div>
                  <div className='grid gap-1.5'>
                    <Label>Internal notes</Label>
                    <Textarea rows={2} value={edit.admin_notes} onChange={(e) => setEdit({ ...edit, admin_notes: e.target.value })} />
                  </div>
                  <Button size='sm' disabled={busy} onClick={() => act(() => axios.patch(`/api/partners/${p.id}`, edit))}>Save</Button>
                </section>
              </>
            )}

            {/* Points */}
            <Separator />
            <section className='space-y-3'>
              <h3 className='font-semibold'>Points</h3>
              {can('adjust partner points') && (
                <div className='grid gap-2 sm:grid-cols-[120px_1fr_auto]'>
                  <Input type='number' placeholder='+/- points' value={adjust.points} onChange={(e) => setAdjust({ ...adjust, points: e.target.value })} />
                  <Input placeholder='Reason (shown to the partner)' value={adjust.note} onChange={(e) => setAdjust({ ...adjust, note: e.target.value })} />
                  <Button
                    size='sm'
                    disabled={busy || !adjust.points || Number(adjust.points) === 0 || adjust.note.trim().length < 3}
                    onClick={() => act(async () => {
                      const res = await axios.post(`/api/partners/${p.id}/points`, { points: Number(adjust.points), note: adjust.note })
                      setAdjust({ points: '', note: '' })
                      axios.get(`/api/partners/${p.id}/transactions`).then(({ data }) => setTransactions(data.transactions))
                      return res
                    })}
                  >
                    Adjust
                  </Button>
                </div>
              )}
              {transactions === null ? (
                <p className='text-sm text-muted-foreground'>Loading ledger…</p>
              ) : transactions.length === 0 ? (
                <p className='text-sm text-muted-foreground'>No points yet.</p>
              ) : (
                <ul className='max-h-80 divide-y overflow-y-auto rounded-md border text-sm'>
                  {transactions.map((t) => (
                    <li key={t.id} className='flex items-center justify-between gap-3 px-3 py-2'>
                      <div className='min-w-0'>
                        <div className='truncate'><span className='font-medium'>{TRANSACTION_LABEL[t.type] ?? t.type}</span>{t.description ? ` · ${t.description}` : ''}</div>
                        <div className='text-xs text-muted-foreground'>{formatDate(t.created_at)}{t.by ? ` · by ${t.by}` : ''}</div>
                      </div>
                      <span className={`shrink-0 tabular-nums font-semibold ${t.points < 0 ? 'text-red-600' : 'text-emerald-600'}`}>{t.points > 0 ? '+' : ''}{formatPoints(t.points)}</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        )}
      </SheetContent>
    </Sheet>
  )
}

function Dt({ children }: { children: React.ReactNode }) {
  return <dt className='text-muted-foreground'>{children}</dt>
}

function Dd({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return <dd className={`min-w-0 break-words ${className}`}>{children}</dd>
}

function Fragmentish({ label, value }: { label: string; value: string }) {
  const href = /^https?:\/\//.test(value) ? value : null
  return (
    <>
      <Dt>{label}</Dt>
      <Dd>{href ? <a className='underline' href={href} target='_blank' rel='noreferrer'>{value}</a> : value}</Dd>
    </>
  )
}
