import { AuthLogo } from '@/components/layout/auth-logo'
import { ThemeProvider } from '@/context/theme-provider'
import { Toaster } from '@/components/ui/sonner'

type AuthLayoutProps = {
  children: React.ReactNode
}

export function AuthLayout({ children }: AuthLayoutProps) {
  return (
    <ThemeProvider>
      <div className='container grid h-svh max-w-none items-center justify-center'>
        <div className='mx-auto flex w-full flex-col justify-center space-y-2 py-8 sm:p-8'>
          <div className='mb-6 flex items-center justify-center'>
            <AuthLogo />
          </div>
          {children}
        </div>
      </div>
      <Toaster position='bottom-right' />
    </ThemeProvider>
  )
}
