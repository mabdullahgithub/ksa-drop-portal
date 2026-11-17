import { useState } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { Settings } from './types'

const FIELDS: { key: string; label: string; hint: string; step?: string }[] = [
  { key: 'approval_bonus_points', label: 'Welcome bonus (points)', hint: 'Awarded once, when an application is approved.' },
  { key: 'activation_points', label: 'Activation (points)', hint: 'When a referred client gets their first delivered shipment.' },
  { key: 'delivery_points', label: 'Per delivered shipment (points)', hint: 'Multiplied by the partner\'s tier.' },
  { key: 'delivery_months', label: 'Earning window (months)', hint: 'How long deliveries earn after activation. 0 switches per-delivery points off.' },
  { key: 'milestone_deliveries', label: 'Milestone (deliveries)', hint: 'A referred client reaching this many delivered shipments. 0 switches it off.' },
  { key: 'milestone_points', label: 'Milestone bonus (points)', hint: 'Awarded once per referred client.' },
  { key: 'silver_min_active_clients', label: 'Silver from (active clients)', hint: '' },
  { key: 'silver_multiplier', label: 'Silver multiplier', hint: 'Applied to per-delivery points.', step: '0.05' },
  { key: 'gold_min_active_clients', label: 'Gold from (active clients)', hint: '' },
  { key: 'gold_multiplier', label: 'Gold multiplier', hint: '', step: '0.05' },
  { key: 'min_redeem_points', label: 'Minimum redemption (points)', hint: 'The smallest payout a partner can request.' },
  { key: 'points_per_sar', label: 'Points per 1 SAR', hint: 'Conversion rate used to value a payout. 2 means every 2 points are worth 1 SAR.', step: '0.01' },
  { key: 'cookie_days', label: 'Referral cookie (days)', hint: 'How long a visitor who opened a referral link stays attributed.' },
  { key: 'terms_version', label: 'Terms version', hint: 'Shown on the application form and stored with each acceptance.' },
]

function RatePreview({ values }: { values: Record<string, string> }) {
  const rate = Number(values.points_per_sar)
  if (!rate || rate <= 0) return null
  const sar = (p: string) => (Number(p || 0) / rate).toLocaleString(undefined, { maximumFractionDigits: 2 })
  const rows: [string, string][] = [
    ['Welcome bonus', values.approval_bonus_points],
    ['Activation', values.activation_points],
    ['Per delivered shipment', values.delivery_points],
    ['Milestone bonus', values.milestone_points],
    ['Minimum redemption', values.min_redeem_points],
  ]
  return (
    <div className='rounded-lg border bg-muted/40 p-4 text-sm'>
      <div className='mb-2 font-medium'>Value at {rate} points = 1 SAR</div>
      <ul className='grid gap-1 sm:grid-cols-2'>
        {rows.map(([label, pts]) => (
          <li key={label} className='flex justify-between gap-3'>
            <span className='text-muted-foreground'>{label}</span>
            <span className='tabular-nums'>{pts || 0} pts ≈ {sar(pts)} SAR</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

export function SettingsTab({ settings }: { settings: Settings }) {
  const [values, setValues] = useState<Record<string, string>>(Object.fromEntries(Object.entries(settings).map(([k, v]) => [k, String(v)])))
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)

  const save = async () => {
    setBusy(true)
    setErrors({})
    try {
      const { data } = await axios.put('/api/partners/settings', values)
      setValues(Object.fromEntries(Object.entries(data.settings as Settings).map(([k, v]) => [k, String(v)])))
      toast.success(data.message ?? 'Saved.')
    } catch (e) {
      if (axios.isAxiosError(e) && e.response?.status === 422) {
        setErrors(e.response.data.errors ?? {})
        toast.error('Check the highlighted fields.')
      } else {
        toast.error('Could not save settings.')
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Card className='max-w-3xl'>
      <CardHeader>
        <CardTitle>Program settings</CardTitle>
        <CardDescription>
          Changes apply to points awarded from now on; nothing already in a ledger is recalculated. The public page reads these live.
        </CardDescription>
      </CardHeader>
      <CardContent className='space-y-5'>
        <div className='grid gap-4 sm:grid-cols-2'>
          {FIELDS.map((f) => (
            <div key={f.key} className='grid gap-1.5'>
              <Label htmlFor={`ps-${f.key}`}>{f.label}</Label>
              <Input
                id={`ps-${f.key}`}
                type={f.key === 'terms_version' ? 'text' : 'number'}
                step={f.step ?? '1'}
                value={values[f.key] ?? ''}
                onChange={(e) => setValues({ ...values, [f.key]: e.target.value })}
              />
              {errors[f.key] ? <p className='text-xs text-destructive'>{errors[f.key][0]}</p> : f.hint && <p className='text-xs text-muted-foreground'>{f.hint}</p>}
            </div>
          ))}
        </div>
        <RatePreview values={values} />
        <Button onClick={save} disabled={busy}>{busy ? 'Saving…' : 'Save settings'}</Button>
      </CardContent>
    </Card>
  )
}
