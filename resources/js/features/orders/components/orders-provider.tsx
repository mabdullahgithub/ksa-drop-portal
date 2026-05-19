import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Order } from '@/types/order'

type OrdersDialogType = 'view' | 'update' | 'delete' | 'export'

type OrdersContextType = {
  open: OrdersDialogType | null
  setOpen: (str: OrdersDialogType | null) => void
  currentRow: Order | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Order | null>>
  selectedRows: number[]
  setSelectedRows: React.Dispatch<React.SetStateAction<number[]>>
}

const OrdersContext = React.createContext<OrdersContextType | null>(null)

export function OrdersProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<OrdersDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Order | null>(null)
  const [selectedRows, setSelectedRows] = useState<number[]>([])

  return (
    <OrdersContext value={{ open, setOpen, currentRow, setCurrentRow, selectedRows, setSelectedRows }}>
      {children}
    </OrdersContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useOrdersContext = () => {
  const ordersContext = React.useContext(OrdersContext)

  if (!ordersContext) {
    throw new Error('useOrdersContext has to be used within <OrdersContext>')
  }

  return ordersContext
}
