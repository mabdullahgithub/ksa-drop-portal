import React, { createContext, useContext, useState } from 'react'
import { type Role } from '../data/schema'

type Open = 'add' | 'edit' | 'delete' | 'view-permissions' | null

interface RolesContextType {
  open: Open
  setOpen: (open: Open) => void
  currentRow: Role | null
  setCurrentRow: (row: Role | null) => void
}

const RolesContext = createContext<RolesContextType | undefined>(undefined)

export function RolesProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState<Open>(null)
  const [currentRow, setCurrentRow] = useState<Role | null>(null)

  return (
    <RolesContext.Provider value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </RolesContext.Provider>
  )
}

export function useRoles() {
  const context = useContext(RolesContext)
  if (!context) {
    throw new Error('useRoles must be used within RolesProvider')
  }
  return context
}
