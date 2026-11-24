import { useTheme } from '@/context/theme-provider'
import { cn } from '@/lib/utils'

type AuthLogoProps = {
  className?: string
}

export function AuthLogo({ className }: AuthLogoProps) {
  const { resolvedTheme } = useTheme()

  const isDark = resolvedTheme === 'dark'

  const logoSrc = isDark
    ? '/ksa-al-logo-files/Logo/ksa-drop-logo-dark.webp'
    : '/ksa-al-logo-files/Logo/ksa-drop-logo-light.webp'

  return (
    <img
      src={logoSrc}
      alt='KSA Drop'
      width={640}
      height={160}
      fetchPriority='high'
      decoding='async'
      className={cn('h-12 w-auto object-contain', className)}
    />
  )
}
