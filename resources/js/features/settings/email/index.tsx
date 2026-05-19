import { ContentSection } from '../components/content-section'
import { EmailPreferencesForm } from './email-preferences-form'
import { EmailSmtpForm } from './email-smtp-form'
import { usePage } from '@inertiajs/react'

export function SettingsEmail() {
  const { isAdmin, emailSettings } = usePage().props as any

  // Debug logging
  console.log('SettingsEmail - isAdmin:', isAdmin)
  console.log('SettingsEmail - emailSettings:', emailSettings)

  return (
    <div className='space-y-6'>
      {isAdmin && (
        <ContentSection
          title='SMTP Configuration'
          desc='Configure email server settings. This section is only visible to administrators.'
        >
          <EmailSmtpForm />
        </ContentSection>
      )}

      <ContentSection
        title='Email Preferences'
        desc='Choose which emails you want to receive. Security emails are always sent.'
      >
        <EmailPreferencesForm />
      </ContentSection>
    </div>
  )
}
