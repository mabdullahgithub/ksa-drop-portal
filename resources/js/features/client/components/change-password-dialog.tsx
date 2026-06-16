import { useState } from 'react'
import { toast } from 'sonner'
import { Eye, EyeOff, RefreshCw, KeyRound } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useClientMutations } from '@/hooks/useClients'
import type { Client } from '@/types/client'

interface ChangePasswordDialogProps {
  client: Client
  open: boolean
  onOpenChange: (open: boolean) => void
}

function generatePassword(length = 16): string {
  const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*'
  const values = new Uint32Array(length)
  crypto.getRandomValues(values)
  return Array.from(values, (v) => charset[v % charset.length]).join('')
}

export function ChangePasswordDialog({ client, open, onOpenChange }: ChangePasswordDialogProps) {
  const { resetPassword, loading } = useClientMutations()
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [show, setShow] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const handleOpenChange = (o: boolean) => {
    if (!o) {
      setPassword('')
      setConfirmation('')
      setShow(false)
      setErrors({})
    }
    onOpenChange(o)
  }

  const handleGenerate = () => {
    const generated = generatePassword()
    setPassword(generated)
    setConfirmation(generated)
    setShow(true)
    setErrors({})
  }

  const handleSubmit = async () => {
    setErrors({})
    const result = await resetPassword(client.id, password, confirmation)
    if (result === true) {
      toast.success('Password updated. The client has been notified by email.')
      handleOpenChange(false)
    } else if (result && typeof result === 'object' && 'errors' in result) {
      setErrors(result.errors)
    } else {
      toast.error('Failed to update password')
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle className='flex items-center gap-2'>
            <KeyRound className='h-4 w-4' />
            Change Password
          </DialogTitle>
          <DialogDescription>
            Set a new password for <strong>{client.company_name}</strong> ({client.user?.email}).
            The client will receive an email confirming the change. Share the new password with them
            through a secure channel.
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-4 py-2'>
          <div className='space-y-2'>
            <div className='flex items-center justify-between'>
              <Label htmlFor='new-password'>New Password</Label>
              <button
                type='button'
                onClick={handleGenerate}
                className='inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors'
                disabled={loading}
              >
                <RefreshCw className='h-3 w-3' />
                Generate
              </button>
            </div>
            <div className='relative'>
              <Input
                id='new-password'
                type={show ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={loading}
                autoComplete='new-password'
                className='pr-9'
              />
              <button
                type='button'
                onClick={() => setShow((s) => !s)}
                className='absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground'
                tabIndex={-1}
              >
                {show ? <EyeOff className='h-4 w-4' /> : <Eye className='h-4 w-4' />}
              </button>
            </div>
            {errors.password && (
              <p className='text-xs text-destructive'>{errors.password[0]}</p>
            )}
          </div>

          <div className='space-y-2'>
            <Label htmlFor='confirm-password'>Confirm Password</Label>
            <Input
              id='confirm-password'
              type={show ? 'text' : 'password'}
              value={confirmation}
              onChange={(e) => setConfirmation(e.target.value)}
              disabled={loading}
              autoComplete='new-password'
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => handleOpenChange(false)} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading || !password || !confirmation}>
            {loading ? 'Saving…' : 'Change Password'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
