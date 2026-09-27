import { AlertCircle, CheckCircle2, Loader2, RotateCw } from 'lucide-react'
import { cn } from '@/lib/utils'
import { statusText, useI18n, type TFunction } from '../i18n'
import type { BatchItem } from '../types'

type Props = {
  items: BatchItem[]
  onRetry: (code: string) => void
  onDone: () => void
}

/** Live list of a batch pick-up, newest first, over the camera. */
export function BatchPanel({ items, onRetry, onDone }: Props) {
  const { t } = useI18n()
  const picked = items.filter((item) => item.state === 'claimed' || item.state === 'already_mine').length
  const problems = items.filter((item) => item.state !== 'claimed' && item.state !== 'already_mine' && item.state !== 'working').length

  return (
    <div className='glass-dark rounded-[28px] p-3'>
      <div className='flex items-center gap-3'>
        <div className='min-w-0 flex-1 ps-1'>
          <p className='text-[15px] font-semibold'>{t('batch_count', { n: picked })}</p>
          {problems > 0 && <p className='text-xs font-medium text-red-300'>{t('batch_problems', { n: problems })}</p>}
        </div>
        <button
          type='button'
          onClick={onDone}
          className='glass-tint glass-press h-10 shrink-0 rounded-full px-5 text-sm font-semibold'
        >
          {t('batch_done')}
        </button>
      </div>

      {items.length === 0 ? (
        <p className='mt-2 px-1 text-xs text-white/75'>{t('batch_hint')}</p>
      ) : (
        <ul className='mt-2.5 max-h-[30vh] space-y-1.5 overflow-y-auto overscroll-contain'>
          {items.map((item) => (
            <li key={item.code}>
              <button
                type='button'
                disabled={item.state !== 'error'}
                onClick={() => onRetry(item.code)}
                className='flex w-full items-center gap-2.5 rounded-2xl bg-white/10 px-3 py-2 text-start'
              >
                <StateIcon state={item.state} />
                <span className='min-w-0 flex-1'>
                  <span className='block truncate text-sm font-semibold'>{item.parcel?.receiver.name ?? item.code}</span>
                  <span className='block truncate font-mono text-[11px] text-white/60' dir='ltr'>
                    {item.parcel?.tracking_number ?? item.code}
                  </span>
                </span>
                <span
                  className={cn(
                    'shrink-0 text-end text-xs font-semibold',
                    item.state === 'claimed' || item.state === 'already_mine' ? 'text-green-300' : item.state === 'working' ? 'text-white/60' : 'text-red-300'
                  )}
                >
                  {stateText(t, item)}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function StateIcon({ state }: { state: BatchItem['state'] }) {
  if (state === 'working') return <Loader2 className='h-5 w-5 shrink-0 animate-spin text-white/70' />
  if (state === 'claimed' || state === 'already_mine') return <CheckCircle2 className='h-5 w-5 shrink-0 text-green-400' />
  if (state === 'error') return <RotateCw className='h-5 w-5 shrink-0 text-red-300' />
  return <AlertCircle className='h-5 w-5 shrink-0 text-red-300' />
}

function stateText(t: TFunction, item: BatchItem): string {
  switch (item.state) {
    case 'working':
      return ''
    case 'claimed':
      return t('claim_claimed')
    case 'already_mine':
      return t('claim_already_mine')
    case 'held_by_other':
      return t('claim_held_by_other', { name: item.parcel?.held_by ?? '—' })
    case 'finished':
      return t('claim_finished', { status: item.parcel ? statusText(t, item.parcel.status) : '' })
    case 'not_ksa_express':
      return t('claim_not_ksa_express')
    case 'not_found':
      return t('claim_not_found')
    default:
      return t('claim_error')
  }
}
