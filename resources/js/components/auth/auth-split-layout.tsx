import { ReactNode } from 'react'
import { ThemeProvider } from '@/context/theme-provider'
import { ThemeSwitch } from '@/components/theme-switch'
import { AuthLogo } from '@/components/layout/auth-logo'

/**
 * The sign-in and signup pages' frame: the brand panel with the drone video on
 * the left, the form on the right. Used as a persistent Inertia layout, so
 * moving between the two pages swaps only the form; the video keeps playing
 * instead of being torn down and fetched again.
 *
 * On large screens the page is one screen high and only the form scrolls.
 */
export function AuthSplitLayout({ children }: { children: ReactNode }) {
  return (
    <ThemeProvider>
      <div className='relative grid min-h-svh grid-cols-1 lg:h-svh lg:grid-cols-[1.6fr_1fr] lg:overflow-hidden'>
        <div className='relative hidden flex-col bg-muted/60 lg:flex'>
          <div className='absolute left-8 top-7'>
            <AuthLogo />
          </div>
          <div className='flex flex-1 items-center justify-center p-10'>
            <video
              className='h-auto w-full max-w-2xl select-none object-contain'
              autoPlay
              loop
              muted
              playsInline
              preload='auto'
              aria-label='KSA Drop'
            >
              {/* HEVC-with-alpha plays only in Safari; the quicktime type keeps
                  Chromium from claiming it and rendering the alpha as black. */}
              <source src='/images/login/drone-with-parcel.mov' type='video/quicktime' />
              <source src='/images/login/drone-with-parcel.webm' type='video/webm' />
            </video>
          </div>
        </div>

        <div className='relative flex flex-col bg-background lg:overflow-y-auto'>
          <div className='flex items-center px-6 pt-6 lg:hidden'>
            <AuthLogo />
          </div>

          <div className='absolute right-6 top-6'>
            <ThemeSwitch />
          </div>

          <div className='flex flex-1 items-center justify-center px-6 py-12'>{children}</div>
        </div>
      </div>
    </ThemeProvider>
  )
}

export const authSplitLayout = (page: ReactNode) => <AuthSplitLayout>{page}</AuthSplitLayout>
