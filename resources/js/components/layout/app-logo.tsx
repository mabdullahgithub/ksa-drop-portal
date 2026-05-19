import { useTheme } from '@/context/theme-provider'
import { useSidebar } from '@/components/ui/sidebar'
import { cn } from '@/lib/utils'

export function AppLogo() {
  const { resolvedTheme } = useTheme()
  const { state, isMobile } = useSidebar()

  const isDark = resolvedTheme === 'dark'
  const isCollapsed = state === 'collapsed' && !isMobile

  const logoSrc = isDark
    ? '/ksa-al-logo-files/Logo/KSA Drop Logo PNG-03.png'
    : '/ksa-al-logo-files/Logo/KSA Drop Logo PNG-02.png'

  return (
    <div
      className={cn(
        'flex items-center justify-center py-2 transition-all duration-200',
        isCollapsed ? 'px-2' : 'px-4'
      )}
    >
      <img
        src={logoSrc}
        alt='KSA Logo'
        className={cn(
          'object-contain transition-all duration-200',
          isCollapsed ? 'h-8 w-8' : 'h-10 w-auto max-w-full'
        )}
      />
    </div>
  )
}
