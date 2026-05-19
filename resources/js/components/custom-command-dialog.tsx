import * as React from 'react'
import { X } from 'lucide-react'
import { Command } from '@/components/ui/command'

interface CustomCommandDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  children: React.ReactNode
}

export function CustomCommandDialog({
  open,
  onOpenChange,
  children,
}: CustomCommandDialogProps) {
  if (!open) return null

  return (
    <>
      {/* Backdrop */}
      <div
        className='fixed inset-0 z-50 bg-black/50 animate-in fade-in-0'
        onClick={() => onOpenChange(false)}
      />

      {/* Modal */}
      <div className='fixed left-[50%] top-[50%] z-50 w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] animate-in fade-in-0 zoom-in-95 sm:max-w-2xl'>
        <div className='relative rounded-lg border bg-background shadow-lg overflow-hidden'>
          {/* Close Button */}
          <button
            onClick={() => onOpenChange(false)}
            className='absolute right-4 top-4 z-10 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2'
          >
            <X className='h-4 w-4' />
            <span className='sr-only'>Close</span>
          </button>

          {/* Content */}
          <Command className='[&_[cmdk-group]:not([hidden])_~[cmdk-group]]:pt-0 [&_[cmdk-input-wrapper]_svg]:h-5 [&_[cmdk-input-wrapper]_svg]:w-5 [&_[cmdk-item]_svg]:h-5 [&_[cmdk-item]_svg]:w-5 **:[[cmdk-group-heading]]:px-2 **:[[cmdk-group-heading]]:font-medium **:[[cmdk-group-heading]]:text-muted-foreground **:[[cmdk-group]]:px-2 **:[[cmdk-input]]:h-12 **:[[cmdk-item]]:px-2 **:[[cmdk-item]]:py-3'>
            {children}
          </Command>
        </div>
      </div>
    </>
  )
}
