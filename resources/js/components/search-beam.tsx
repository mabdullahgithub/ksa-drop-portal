import { useState } from 'react'
import { BorderBeam } from 'border-beam'
import { useTheme } from '@/context/theme-provider'

type SearchBeamProps = {
  /** The search input to wrap. */
  children: React.ReactNode
}

/**
 * Wraps a table search input with an animated border beam that only runs while
 * the input is focused. The focus tracker uses `display: contents` so the beam
 * wrapper takes the input's place in the layout and nothing shifts.
 *
 * Deliberately not applied to the global command-palette search in
 * `components/search.tsx`.
 */
export function SearchBeam({ children }: SearchBeamProps) {
  const { resolvedTheme } = useTheme()
  const [focused, setFocused] = useState(false)

  return (
    <div
      className='contents'
      onFocus={() => setFocused(true)}
      onBlur={() => setFocused(false)}
    >
      <BorderBeam
        size='md'
        colorVariant='colorful'
        strength={0.7}
        theme={resolvedTheme}
        active={focused}
      >
        {children}
      </BorderBeam>
    </div>
  )
}
