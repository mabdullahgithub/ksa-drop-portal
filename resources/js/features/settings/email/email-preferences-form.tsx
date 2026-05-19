import { useForm, usePage } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Lock } from 'lucide-react'

interface EmailPreference {
  id: string
  label: string
  description: string
  key: keyof EmailPreferencesData
  required?: boolean
  category: 'security' | 'auth' | 'user' | 'settings' | 'communication'
}

interface EmailPreferencesData {
  // Security emails (always enabled)
  verify_email: boolean
  reset_password: boolean
  password_changed: boolean
  two_factor_enabled: boolean
  two_factor_disabled: boolean

  // User management
  welcome_user: boolean
  user_created_admin: boolean
  user_updated: boolean

  // Settings & Communication
  welcome: boolean
  settings_updated: boolean
}

const EMAIL_PREFERENCES: EmailPreference[] = [
  // Security & Authentication (Always enabled)
  {
    id: 'verify_email',
    label: 'Email Verification',
    description: 'Email address verification requests',
    key: 'verify_email',
    required: true,
    category: 'security',
  },
  {
    id: 'reset_password',
    label: 'Password Reset',
    description: 'Password reset requests and links',
    key: 'reset_password',
    required: true,
    category: 'security',
  },
  {
    id: 'password_changed',
    label: 'Password Changed',
    description: 'Confirmation when password is successfully changed',
    key: 'password_changed',
    required: true,
    category: 'security',
  },
  {
    id: 'two_factor_enabled',
    label: 'Two-Factor Authentication Enabled',
    description: 'Notification when 2FA is activated',
    key: 'two_factor_enabled',
    required: true,
    category: 'security',
  },
  {
    id: 'two_factor_disabled',
    label: 'Two-Factor Authentication Disabled',
    description: 'Notification when 2FA is deactivated',
    key: 'two_factor_disabled',
    required: true,
    category: 'security',
  },

  // User Management
  {
    id: 'welcome_user',
    label: 'Welcome Email with Credentials',
    description: 'Send login credentials to newly created users',
    key: 'welcome_user',
    category: 'user',
  },
  {
    id: 'user_created_admin',
    label: 'User Created (Admin Notification)',
    description: 'Notify admins when a new user is created',
    key: 'user_created_admin',
    category: 'user',
  },
  {
    id: 'user_updated',
    label: 'User Account Updated',
    description: 'Notify when user account details are modified',
    key: 'user_updated',
    category: 'user',
  },

  // Communication & Settings
  {
    id: 'welcome',
    label: 'Welcome Email (Registration)',
    description: 'Welcome message when users register themselves',
    key: 'welcome',
    category: 'communication',
  },
  {
    id: 'settings_updated',
    label: 'Settings Updated',
    description: 'Notify when account settings are changed',
    key: 'settings_updated',
    category: 'settings',
  },
]

const CATEGORY_LABELS = {
  security: 'Security & Authentication',
  auth: 'Authentication',
  user: 'User Management',
  settings: 'Settings',
  communication: 'Communication',
}

export function EmailPreferencesForm() {
  const { preference } = usePage().props as any

  const { data, setData, put, processing } = useForm<EmailPreferencesData>({
    // Security (always enabled)
    verify_email: true,
    reset_password: true,
    password_changed: true,
    two_factor_enabled: true,
    two_factor_disabled: true,

    // User management
    welcome_user: preference?.welcome_user ?? true,
    user_created_admin: preference?.user_created_admin ?? false,
    user_updated: preference?.user_updated ?? false,

    // Communication
    welcome: preference?.welcome ?? true,
    settings_updated: preference?.settings_updated ?? true,
  })

  const handleSubmit: FormEventHandler = (e) => {
    e.preventDefault()
    put(route('settings.email.preferences.update'), {
      onSuccess: () => {
        toast.success('Email preferences updated successfully')
      },
      onError: () => {
        toast.error('Failed to update email preferences')
      },
    })
  }

  const groupedPreferences = EMAIL_PREFERENCES.reduce((acc, pref) => {
    if (!acc[pref.category]) {
      acc[pref.category] = []
    }
    acc[pref.category].push(pref)
    return acc
  }, {} as Record<string, EmailPreference[]>)

  return (
    <form onSubmit={handleSubmit} className='space-y-8'>
      <div className='space-y-6'>
        {Object.entries(groupedPreferences).map(([category, preferences]) => (
          <div key={category} className='space-y-3'>
            <div className='flex items-center gap-2'>
              <h3 className='text-sm font-medium text-muted-foreground'>
                {CATEGORY_LABELS[category as keyof typeof CATEGORY_LABELS]}
              </h3>
              {category === 'security' && (
                <span className='text-xs text-muted-foreground flex items-center gap-1'>
                  <Lock className='h-3 w-3' />
                  Always enabled
                </span>
              )}
            </div>
            <div className='space-y-2'>
              {preferences.map((pref) => (
                <div
                  key={pref.id}
                  className='flex items-start gap-3 rounded-lg border px-4 py-3 hover:bg-accent/50 transition-colors'
                >
                  <Checkbox
                    id={pref.id}
                    checked={data[pref.key]}
                    onCheckedChange={(checked) =>
                      setData(pref.key, checked as boolean)
                    }
                    disabled={pref.required}
                    className='mt-0.5'
                  />
                  <label
                    htmlFor={pref.id}
                    className='flex-1 cursor-pointer space-y-0.5'
                  >
                    <div className='flex items-center gap-2'>
                      <span className='text-sm font-medium leading-none'>
                        {pref.label}
                      </span>
                      {pref.required && (
                        <Lock className='h-3 w-3 text-muted-foreground' />
                      )}
                    </div>
                    <p className='text-xs text-muted-foreground'>
                      {pref.description}
                    </p>
                  </label>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>

      <Button type='submit' disabled={processing}>
        {processing ? 'Updating...' : 'Update preferences'}
      </Button>
    </form>
  )
}
