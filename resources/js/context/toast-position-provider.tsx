import { createContext, useContext } from 'react'
import { usePage } from '@inertiajs/react'
import type { Position } from 'sonner'

type ToastPositionContextType = {
  toastPosition: Position
}

const ToastPositionContext = createContext<ToastPositionContextType | undefined>(
  undefined
)

export function ToastPositionProvider({
  children,
}: {
  children: React.ReactNode
}) {
  const { preferences } = usePage().props as any

  const toastPosition: Position =
    preferences?.toast_position || 'bottom-right'

  return (
    <ToastPositionContext.Provider value={{ toastPosition }}>
      {children}
    </ToastPositionContext.Provider>
  )
}

export function useToastPosition() {
  const context = useContext(ToastPositionContext)
  if (context === undefined) {
    throw new Error(
      'useToastPosition must be used within a ToastPositionProvider'
    )
  }
  return context
}
