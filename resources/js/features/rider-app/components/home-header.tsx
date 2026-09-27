import { Search, Settings2, X } from 'lucide-react'
import { useI18n } from '../i18n'
import type { Me } from '../types'
import { RiderPhoto } from './rider-photo'

type Props = {
  me: Me | null
  onProfile: () => void
  query: string
  onQuery: (query: string) => void
  /** Dot on the filter button: the list isn't showing its default. */
  filterActive: boolean
  onFilter: () => void
}

/**
 * Home's orange header: greeting, the rider's photo, and the search bar
 * sitting half over its bottom edge.
 */
export function HomeHeader({ me, onProfile, query, onQuery, filterActive, onFilter }: Props) {
  const { t } = useI18n()
  const firstName = me?.rider.name.split(' ')[0] ?? ''

  return (
    <header>
      <div className='relative overflow-hidden rounded-b-[36px] bg-brand px-5 pb-[68px] pt-[calc(env(safe-area-inset-top)+28px)] text-white'>
        <Waves />

        <div className='relative flex items-center gap-4'>
          <div className='min-w-0 flex-1'>
            <p className='truncate text-[15px] font-semibold text-white/90'>{me ? t('hello', { name: firstName }) : ' '}</p>
            <h1 className='mt-2.5 whitespace-pre-line text-[clamp(23px,7vw,28px)] font-bold leading-[1.15]'>{t('home_headline')}</h1>
          </div>

          <button type='button' onClick={onProfile} aria-label={t('nav_profile')} className='relative shrink-0 transition-transform active:scale-95'>
            {me ? (
              <RiderPhoto name={me.rider.name} photoUrl={me.rider.photo_url} className='h-16 w-16 text-lg ring-[3px] ring-white/60' />
            ) : (
              <span className='block h-16 w-16 rounded-full bg-white/10 ring-[3px] ring-white/20' />
            )}
            <span className='absolute bottom-0.5 start-0.5 h-3.5 w-3.5 rounded-full border-2 border-white/90 bg-ink' />
          </button>
        </div>
      </div>

      <div className='relative -mt-10 px-4'>
        <div className='flex h-[60px] items-center gap-2 rounded-[20px] bg-surface pe-2 ps-4 shadow-[0_12px_32px_-14px_rgb(0_0_0/0.3)]'>
          <Search className='h-[22px] w-[22px] shrink-0 text-muted-foreground' strokeWidth={1.75} />
          <input
            value={query}
            onChange={(event) => onQuery(event.target.value)}
            onKeyDown={(event) => event.key === 'Enter' && event.currentTarget.blur()}
            type='search'
            enterKeyHint='search'
            autoComplete='off'
            placeholder={t('search_placeholder')}
            aria-label={t('search_placeholder')}
            className='h-full min-w-0 flex-1 appearance-none bg-transparent px-1 outline-none placeholder:text-muted-foreground/70 [&::-webkit-search-cancel-button]:hidden'
          />
          {query && (
            <button
              type='button'
              onClick={() => onQuery('')}
              aria-label={t('clear_search')}
              className='flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted-foreground active:bg-foreground/5'
            >
              <X className='h-5 w-5' />
            </button>
          )}
          <button
            type='button'
            onClick={onFilter}
            aria-label={t('filter')}
            className='relative flex h-11 w-11 shrink-0 items-center justify-center rounded-[14px] bg-brand text-white transition-transform active:scale-95'
          >
            <Settings2 className='h-5 w-5' strokeWidth={2.25} />
            {filterActive && <span className='absolute -end-0.5 -top-0.5 h-3 w-3 rounded-full bg-ink ring-2 ring-surface dark:bg-white' />}
          </button>
        </div>
      </div>
    </header>
  )
}

/** A ribbon of thin lines that twists near the top — the header's texture. */
const WAVES = (() => {
  const width = 400
  const height = 260
  const lines = 42
  const paths: string[] = []
  for (let i = 0; i < lines; i++) {
    const t = i / (lines - 1) - 0.5
    let d = ''
    for (let x = -20; x <= width + 20; x += 8) {
      const u = x / width
      const twist = Math.cos(u * Math.PI * 1.2 - 0.77)
      const center = height * (0.5 - 0.3 * Math.sin(u * Math.PI * 1.1 + 0.3))
      const drift = 18 * Math.sin(u * 5.5 + t * 5) * (1 - 0.6 * Math.abs(twist))
      const y = center + t * height * 0.9 * twist + drift
      d += `${d ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`
    }
    paths.push(d)
  }
  return paths
})()

function Waves() {
  return (
    <svg
      aria-hidden
      viewBox='0 0 400 260'
      preserveAspectRatio='xMidYMid slice'
      className='pointer-events-none absolute inset-0 h-full w-full rtl:-scale-x-100'
    >
      {WAVES.map((d, i) => (
        <path key={i} d={d} fill='none' stroke='white' strokeOpacity={0.14} strokeWidth={0.7} />
      ))}
    </svg>
  )
}
