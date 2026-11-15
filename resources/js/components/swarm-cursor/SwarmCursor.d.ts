import type { CSSProperties, ReactNode } from 'react'

export interface SwarmCursorProps {
  children?: ReactNode
  count?: number
  size?: number
  shape?: 'dot' | 'streak' | 'square'
  color?: string
  accentColor?: string
  spread?: number
  speed?: number
  swirl?: number
  tail?: number
  trail?: number
  wander?: number
  depth?: number
  targets?: string
  idle?: 'roam' | 'gather' | 'hide'
  clickEffect?: 'burst' | 'pulse' | 'none'
  hideCursor?: boolean
  intro?: boolean
  enabled?: boolean
  lightMode?: boolean
  dpr?: number
  className?: string
  style?: CSSProperties
}

declare const SwarmCursor: (props: SwarmCursorProps) => JSX.Element
export default SwarmCursor
