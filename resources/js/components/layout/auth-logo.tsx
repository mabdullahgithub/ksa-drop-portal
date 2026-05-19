import { useTheme } from '@/context/theme-provider'
import { cn } from '@/lib/utils'

type AuthLogoProps = {
  className?: string
}

export function AuthLogo({ className }: AuthLogoProps) {
  const { resolvedTheme } = useTheme()

  const isDark = resolvedTheme === 'dark'

  const logoSrc = isDark
    ? '/ksa-al-logo-files/Logo/KSA Drop Logo PNG-03.png'
    : '/ksa-al-logo-files/Logo/KSA Drop Logo PNG-02.png'

  return (
    <img
      src={logoSrc}
      alt='KSA Drop'
      className={cn('h-12 w-auto object-contain', className)}
    />
  )
}
