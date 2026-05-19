import React, { createContext, useContext, useState } from 'react'
import { type Permission } from '../data/schema'

type Open = 'edit' | null

interface PermissionsContextType {
  open: Open
  setOpen: (open: Open) => void
  currentRow: Permission | null
  setCurrentRow: (row: Permission | null) => void
}

const PermissionsContext = createContext<PermissionsContextType | undefined>(undefined)

export function PermissionsProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState<Open>(null)
  const [currentRow, setCurrentRow] = useState<Permission | null>(null)

  return (
    <PermissionsContext.Provider value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </PermissionsContext.Provider>
  )
}

export function usePermissions() {
  const context = useContext(PermissionsContext)
  if (!context) {
    throw new Error('usePermissions must be used within PermissionsProvider')
  }
  return context
}
