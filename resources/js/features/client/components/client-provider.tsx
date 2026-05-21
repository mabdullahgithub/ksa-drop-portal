import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import type { Client } from '@/types/client'

type ClientDialogType = 'view' | 'create' | 'edit' | 'delete' | 'export'

type ClientContextType = {
  open: ClientDialogType | null
  setOpen: (str: ClientDialogType | null) => void
  currentRow: Client | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Client | null>>
  selectedRows: number[]
  setSelectedRows: React.Dispatch<React.SetStateAction<number[]>>
}

const ClientContext = React.createContext<ClientContextType | null>(null)

export function ClientProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<ClientDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Client | null>(null)
  const [selectedRows, setSelectedRows] = useState<number[]>([])

  return (
    <ClientContext value={{ open, setOpen, currentRow, setCurrentRow, selectedRows, setSelectedRows }}>
      {children}
    </ClientContext>
  )
}

export const useClientContext = () => {
  const ctx = React.useContext(ClientContext)
  if (!ctx) throw new Error('useClientContext must be used within ClientProvider')
  return ctx
}
