import type { CSSProperties, FC } from 'react'

export type AntigravityProps = {
  count?: number
  magnetRadius?: number
  ringRadius?: number
  waveSpeed?: number
  waveAmplitude?: number
  waveCount?: number
  particleSize?: number
  lerpSpeed?: number
  color?: string
  colors?: string[]
  colorMode?: 'conic' | 'radial' | 'random'
  colorSpeed?: number
  autoAnimate?: boolean
  particleVariance?: number
  rotationSpeed?: number
  depthFactor?: number
  pulseSpeed?: number
  particleShape?: 'capsule' | 'sphere' | 'box' | 'tetrahedron'
  fieldStrength?: number
  ambient?: number
  interactive?: boolean
  clickBurst?: boolean
  intro?: boolean
  lightMode?: boolean
  opacity?: number
  maxDevicePixelRatio?: number
  className?: string
  style?: CSSProperties
}

declare const Antigravity: FC<AntigravityProps>
export default Antigravity
