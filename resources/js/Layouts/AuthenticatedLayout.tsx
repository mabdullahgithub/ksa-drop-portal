import { cn } from '@/lib/utils'
import { getCookie } from '@/lib/cookies'
import { usePage } from '@inertiajs/react'
import { ThemeProvider } from '@/context/theme-provider'
import { DirectionProvider } from '@/context/direction-provider'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { ToastPositionProvider, useToastPosition } from '@/context/toast-position-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { AppSidebar } from '@/components/layout/app-sidebar'
import { ImpersonateBanner } from '@/components/layout/impersonate-banner'
import { SkipToMain } from '@/components/skip-to-main'
import { Toaster } from '@/components/ui/sonner'
import type { PageProps } from '@/types'

type AuthenticatedLayoutProps = {
  children: React.ReactNode
}

function ToasterWithPosition() {
  const { toastPosition } = useToastPosition()
  return <Toaster position={toastPosition} />
}

function LayoutInner({ children }: AuthenticatedLayoutProps) {
  const { auth } = usePage<PageProps>().props
  const isImpersonating = !!auth.impersonating
  const defaultOpen = getCookie('sidebar_state') !== 'false'

  return (
    <div
      data-impersonating={isImpersonating || undefined}
      className={cn(isImpersonating && 'pt-9')}
    >
      <ImpersonateBanner />
      <SidebarProvider defaultOpen={defaultOpen}>
        <SkipToMain />
        <AppSidebar />
        <SidebarInset
          className={cn(
            '@container/content',
            'has-data-[layout=fixed]:h-svh',
            'peer-data-[variant=inset]:has-data-[layout=fixed]:h-[calc(100svh-(var(--spacing)*4))]'
          )}
        >
          {children}
        </SidebarInset>
      </SidebarProvider>
    </div>
  )
}

export default function AuthenticatedLayout({
  children,
}: AuthenticatedLayoutProps) {
  return (
    <ThemeProvider>
      <ToastPositionProvider>
        <DirectionProvider>
          <SearchProvider>
            <LayoutProvider>
              <LayoutInner>{children}</LayoutInner>
            </LayoutProvider>
          </SearchProvider>
        </DirectionProvider>
        <ToasterWithPosition />
      </ToastPositionProvider>
    </ThemeProvider>
  )
}
