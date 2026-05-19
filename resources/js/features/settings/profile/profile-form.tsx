import { useRef, useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import { Camera, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function ProfileForm() {
  const { auth } = usePage().props
  const user = auth.user
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null)

  const profileForm = useForm({
    name: user.name,
    email: user.email,
  })

  const avatarForm = useForm<{ avatar: File | null }>({
    avatar: null,
  })

  const removeAvatarForm = useForm({})

  const initials = user.name
    .split(' ')
    .map((n: string) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)

  function handleAvatarChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return

    setAvatarPreview(URL.createObjectURL(file))
    avatarForm.setData('avatar', file)

    avatarForm.post(route('settings.avatar.update'), {
      forceFormData: true,
      onSuccess: () => {
        setAvatarPreview(null)
        toast.success('Profile picture updated.')
      },
      onError: () => setAvatarPreview(null),
    })
  }

  function handleRemoveAvatar() {
    removeAvatarForm.delete(route('settings.avatar.remove'), {
      onSuccess: () => {
        setAvatarPreview(null)
        toast.success('Profile picture removed.')
      },
    })
  }

  function handleProfileSubmit(e: React.FormEvent) {
    e.preventDefault()
    profileForm.post(route('settings.profile.update'), {
      onSuccess: () => toast.success('Profile updated successfully.'),
    })
  }

  const avatarSrc = avatarPreview || (user.avatar ? `/storage/${user.avatar}` : undefined)

  return (
    <div className='space-y-8'>
      <div className='flex items-center gap-6'>
        <div className='relative'>
          <Avatar className='h-20 w-20'>
            <AvatarImage src={avatarSrc} alt={user.name} />
            <AvatarFallback className='text-lg'>{initials}</AvatarFallback>
          </Avatar>
          <button
            type='button'
            onClick={() => fileInputRef.current?.click()}
            className='bg-primary text-primary-foreground absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full shadow-sm transition-opacity hover:opacity-90'
          >
            <Camera size={14} />
          </button>
          <input
            ref={fileInputRef}
            type='file'
            accept='image/jpeg,image/png,image/webp'
            className='hidden'
            onChange={handleAvatarChange}
          />
        </div>
        <div className='space-y-1'>
          <h4 className='text-sm font-medium'>Profile Picture</h4>
          <p className='text-muted-foreground text-xs'>
            JPG, PNG or WebP. Max 2MB.
          </p>
          <div className='flex gap-2'>
            <Button
              type='button'
              variant='outline'
              size='sm'
              onClick={() => fileInputRef.current?.click()}
              disabled={avatarForm.processing}
            >
              Upload
            </Button>
            {user.avatar && (
              <Button
                type='button'
                variant='outline'
                size='sm'
                onClick={handleRemoveAvatar}
                disabled={removeAvatarForm.processing}
              >
                <Trash2 size={14} />
                Remove
              </Button>
            )}
          </div>
          {avatarForm.errors.avatar && (
            <p className='text-destructive text-xs'>{avatarForm.errors.avatar}</p>
          )}
        </div>
      </div>

      <form onSubmit={handleProfileSubmit} className='space-y-6'>
        <div className='space-y-2'>
          <Label htmlFor='name'>Name</Label>
          <Input
            id='name'
            value={profileForm.data.name}
            onChange={(e) => profileForm.setData('name', e.target.value)}
          />
          {profileForm.errors.name && (
            <p className='text-destructive text-xs'>{profileForm.errors.name}</p>
          )}
        </div>

        <div className='space-y-2'>
          <Label htmlFor='email'>Email</Label>
          <Input
            id='email'
            type='email'
            value={profileForm.data.email}
            onChange={(e) => profileForm.setData('email', e.target.value)}
          />
          {profileForm.errors.email && (
            <p className='text-destructive text-xs'>{profileForm.errors.email}</p>
          )}
        </div>

        <Button type='submit' disabled={profileForm.processing}>
          Save Changes
        </Button>
      </form>
    </div>
  )
}
