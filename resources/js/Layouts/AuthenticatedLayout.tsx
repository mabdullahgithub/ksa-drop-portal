import { cn } from '@/lib/utils'
import { getCookie } from '@/lib/cookies'
import { ThemeProvider } from '@/context/theme-provider'
import { DirectionProvider } from '@/context/direction-provider'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { ToastPositionProvider, useToastPosition } from '@/context/toast-position-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { AppSidebar } from '@/components/layout/app-sidebar'
import { SkipToMain } from '@/components/skip-to-main'
import { Toaster } from '@/components/ui/sonner'

type AuthenticatedLayoutProps = {
  children: React.ReactNode
}

function ToasterWithPosition() {
  const { toastPosition } = useToastPosition()
  return <Toaster position={toastPosition} />
}

export default function AuthenticatedLayout({
  children,
}: AuthenticatedLayoutProps) {
  const defaultOpen = getCookie('sidebar_state') !== 'false'
  return (
    <ThemeProvider>
      <ToastPositionProvider>
        <DirectionProvider>
          <SearchProvider>
            <LayoutProvider>
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
            </LayoutProvider>
          </SearchProvider>
        </DirectionProvider>
        <ToasterWithPosition />
      </ToastPositionProvider>
    </ThemeProvider>
  )
}
