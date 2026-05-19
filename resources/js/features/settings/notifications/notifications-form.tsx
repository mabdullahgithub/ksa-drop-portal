import { z } from 'zod'
import { useForm } from '@inertiajs/react'
import { FormEventHandler } from 'react'
import { toast } from 'sonner'
import { Link, usePage } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Switch } from '@/components/ui/switch'
import {
  ArrowUpLeft,
  ArrowUp,
  ArrowUpRight,
  ArrowDownLeft,
  ArrowDown,
  ArrowDownRight
} from 'lucide-react'

export function NotificationsForm() {
  const { preference } = usePage().props as any

  const { data, setData, put, processing, errors } = useForm({
    toast_position: preference?.toast_position || 'bottom-right',
    notification_type: preference?.notification_type || 'all',
    mobile_notifications: preference?.mobile_notifications || false,
    in_app_notifications: preference?.in_app_notifications || true,
  })

  const handleSubmit: FormEventHandler = (e) => {
    e.preventDefault()
    put(route('settings.notifications.update'), {
      onSuccess: () => {
        toast.success('Notification preferences updated successfully')
      },
      onError: () => {
        toast.error('Failed to update notification preferences')
      },
    })
  }

  return (
    <form onSubmit={handleSubmit} className='space-y-8'>
        <div className='relative space-y-3'>
          <Label>Toast Position</Label>
          <p className='text-sm text-muted-foreground'>
            Choose where toast notifications appear on your screen.
          </p>
          <RadioGroup
            value={data.toast_position}
            onValueChange={(value) => setData('toast_position', value)}
            className='grid grid-cols-2 gap-4 sm:grid-cols-3'
          >
            {[
              { value: 'top-left', label: 'Top Left', icon: ArrowUpLeft },
              { value: 'top-center', label: 'Top Center', icon: ArrowUp },
              { value: 'top-right', label: 'Top Right', icon: ArrowUpRight },
              { value: 'bottom-left', label: 'Bottom Left', icon: ArrowDownLeft },
              { value: 'bottom-center', label: 'Bottom Center', icon: ArrowDown },
              { value: 'bottom-right', label: 'Bottom Right', icon: ArrowDownRight },
            ].map(({ value, label, icon: Icon }) => (
              <div key={value}>
                <RadioGroupItem
                  value={value}
                  id={value}
                  className='peer sr-only'
                />
                <Label
                  htmlFor={value}
                  className='flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary cursor-pointer'
                >
                  <Icon className='mb-3 h-6 w-6' />
                  <span className='text-sm font-normal'>{label}</span>
                </Label>
              </div>
            ))}
          </RadioGroup>
          {errors.toast_position && (
            <p className='text-sm font-medium text-destructive'>
              {errors.toast_position}
            </p>
          )}
        </div>

        <div className='relative space-y-3'>
          <Label>Notify me about...</Label>
          <RadioGroup
            value={data.notification_type}
            onValueChange={(value) => setData('notification_type', value)}
            className='flex flex-col gap-2'
          >
            <div className='flex items-center space-x-2'>
              <RadioGroupItem value='all' id='all' />
              <Label htmlFor='all' className='font-normal cursor-pointer'>
                All new messages
              </Label>
            </div>
            <div className='flex items-center space-x-2'>
              <RadioGroupItem value='mentions' id='mentions' />
              <Label htmlFor='mentions' className='font-normal cursor-pointer'>
                Direct messages and mentions
              </Label>
            </div>
            <div className='flex items-center space-x-2'>
              <RadioGroupItem value='none' id='none' />
              <Label htmlFor='none' className='font-normal cursor-pointer'>
                Nothing
              </Label>
            </div>
          </RadioGroup>
          {errors.notification_type && (
            <p className='text-sm font-medium text-destructive'>
              {errors.notification_type}
            </p>
          )}
        </div>

        <Button type='submit' disabled={processing}>
          {processing ? 'Updating...' : 'Update notifications'}
        </Button>
      </form>
  )
}
