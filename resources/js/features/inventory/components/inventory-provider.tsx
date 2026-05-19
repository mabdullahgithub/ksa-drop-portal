import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Product } from '@/types/product'

type InventoryDialogType = 'view' | 'update' | 'delete'
type ViewMode = 'table' | 'card'

type InventoryContextType = {
  open: InventoryDialogType | null
  setOpen: (str: InventoryDialogType | null) => void
  currentRow: Product | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Product | null>>
  selectedRows: number[]
  setSelectedRows: React.Dispatch<React.SetStateAction<number[]>>
  viewMode: ViewMode
  setViewMode: React.Dispatch<React.SetStateAction<ViewMode>>
}

const InventoryContext = React.createContext<InventoryContextType | null>(null)

export function InventoryProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<InventoryDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Product | null>(null)
  const [selectedRows, setSelectedRows] = useState<number[]>([])
  const [viewMode, setViewMode] = useState<ViewMode>('table')

  return (
    <InventoryContext value={{ open, setOpen, currentRow, setCurrentRow, selectedRows, setSelectedRows, viewMode, setViewMode }}>
      {children}
    </InventoryContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useInventoryContext = () => {
  const ctx = React.useContext(InventoryContext)
  if (!ctx) throw new Error('useInventoryContext must be used within <InventoryProvider>')
  return ctx
}
