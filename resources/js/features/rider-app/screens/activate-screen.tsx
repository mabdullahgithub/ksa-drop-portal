import { useEffect, useState } from 'react'
import { Copy, Download, Languages, Loader2, PlusSquare, Share } from 'lucide-react'
import { toast } from 'sonner'
import { api, ApiError } from '../api'
import { useI18n } from '../i18n'
import { consumeInstallPrompt, isInAppBrowser, isStandalone, onInstallPrompt, platform, type InstallPromptEvent } from '../lib/device'
import { assetUrl, host, type SignInAnswer } from '../lib/host'
import { SignInScreen } from './sign-in-screen'

/**
 * The page the activation link opens.
 *
 * In a browser tab it only helps the rider install — it doesn't use the link
 * up. The installed app starts on this same link (it's the manifest's
 * start_url) and signs itself in on first launch. That matters on iPhone,
 * where the home screen app has its own cookies, separate from Safari.
 */
export function ActivateScreen({ token, name }: { token: string; name?: string | null }) {
  const standalone = isStandalone()
  const [failed, setFailed] = useState<string | null>(null)

  const claim = async () => {
    try {
      await host().signedIn(await api.post<SignInAnswer>('/rider/api/activate', { token, standalone: isStandalone() }))
    } catch (e) {
      setFailed((e as ApiError).code ?? 'link_invalid')
    }
  }

  useEffect(() => {
    if (standalone) claim()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  if (failed) {
    return <SignInScreen reason={failed} />
  }

  if (standalone) {
    return <Signing />
  }

  return <InstallGuide name={name} onUseInBrowser={claim} />
}

function Signing() {
  const { t } = useI18n()
  return (
    <div className='flex min-h-dvh flex-col items-center justify-center gap-4'>
      <img src={assetUrl('/rider-icons/icon-192.png')} alt='' className='h-20 w-20 rounded-2xl' />
      <Loader2 className='h-8 w-8 animate-spin text-brand' />
      <p className='text-base font-semibold'>{t('signing_in')}</p>
    </div>
  )
}

function InstallGuide({ name, onUseInBrowser }: { name?: string | null; onUseInBrowser: () => void }) {
  const { t, toggle } = useI18n()
  const os = platform()
  const inApp = isInAppBrowser()

  const [prompt, setPrompt] = useState<InstallPromptEvent | null>(null)
  const [installed, setInstalled] = useState(false)

  useEffect(() => {
    const onInstalled = () => setInstalled(true)
    window.addEventListener('appinstalled', onInstalled)
    const unsubscribe = onInstallPrompt(setPrompt)
    return () => {
      window.removeEventListener('appinstalled', onInstalled)
      unsubscribe()
    }
  }, [])

  const install = async () => {
    if (!prompt) return
    await prompt.prompt()
    const { outcome } = await prompt.userChoice
    if (outcome === 'accepted') setInstalled(true)
    // A prompt can only be shown once.
    consumeInstallPrompt()
  }

  const copyLink = async () => {
    try {
      await navigator.clipboard.writeText(window.location.href)
      toast.success(t('link_copied'))
    } catch {
      // Clipboard blocked: the rider can still long-press the address bar.
    }
  }

  const useInBrowser = () => {
    if (window.confirm(t('use_in_browser_confirm'))) onUseInBrowser()
  }

  return (
    <div className='flex min-h-dvh flex-col px-6 pb-[calc(env(safe-area-inset-bottom)+24px)] pt-[calc(env(safe-area-inset-top)+16px)]'>
      <div className='flex justify-end'>
        <button type='button' onClick={toggle} className='glass glass-press flex h-10 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold'>
          <Languages className='h-4 w-4' />
          {t('language')}
        </button>
      </div>

      <div className='mt-4 flex flex-col items-center text-center'>
        <img src={assetUrl('/rider-icons/icon-512.png')} alt='' className='h-20 w-20 rounded-3xl shadow-md' />
        <h1 className='mt-4 text-xl font-bold'>{name ? t('welcome', { name }) : t('app_name')}</h1>
        <p className='mt-1.5 text-sm text-muted-foreground'>{t('activate_intro')}</p>
      </div>

      <div className='mt-8 space-y-4'>
        {inApp && (
          <div className='glass-tint space-y-3 rounded-[24px] p-4' style={{ '--tint': '#d97706' } as React.CSSProperties}>
            <p className='font-semibold'>{t('in_app_browser')}</p>
            <button type='button' onClick={copyLink} className='flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-white/70 text-sm font-semibold text-amber-950'>
              <Copy className='h-5 w-5' />
              {t('copy_link')}
            </button>
          </div>
        )}

        {installed ? (
          <p className='glass-tint rounded-[24px] p-4 text-center text-base font-semibold' style={{ '--tint': '#16a34a' } as React.CSSProperties}>
            {t('installed_open')}
          </p>
        ) : os === 'ios' ? (
          <div className='glass rounded-[28px] p-5'>
            <p className='mb-3 font-bold'>{t('ios_title')}</p>
            <ol className='space-y-3'>
              <Step n={1} icon={<Share className='h-5 w-5 text-sky-600' />} text={t('ios_step_share')} />
              <Step n={2} icon={<PlusSquare className='h-5 w-5' />} text={t('ios_step_add')} />
              <Step n={3} icon={<img src={assetUrl('/rider-icons/icon-192.png')} alt='' className='h-5 w-5 rounded' />} text={t('ios_step_open')} />
            </ol>
            <p className='mt-3 text-xs text-muted-foreground'>{t('ios_open_in_safari')}</p>
          </div>
        ) : prompt ? (
          <button
            type='button'
            onClick={install}
            className='glass-tint glass-press flex h-12 w-full items-center justify-center gap-2 rounded-full text-[15px] font-bold'
          >
            <Download className='h-5 w-5' />
            {t('install_app')}
          </button>
        ) : (
          <div className='glass space-y-2 rounded-[28px] p-5 text-sm'>
            <p className='font-semibold'>{t('android_already_installed')}</p>
            <p className='text-muted-foreground'>{t('android_no_prompt')}</p>
          </div>
        )}
      </div>

      <button type='button' onClick={useInBrowser} className='mt-auto pt-8 text-center text-sm text-muted-foreground underline'>
        {t('use_in_browser')}
      </button>
    </div>
  )
}

function Step({ n, icon, text }: { n: number; icon: React.ReactNode; text: string }) {
  return (
    <li className='flex items-center gap-3'>
      <span className='flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold'>{n}</span>
      <span className='glass-lite flex h-8 w-8 shrink-0 items-center justify-center rounded-xl'>{icon}</span>
      <span className='text-sm'>{text}</span>
    </li>
  )
}

