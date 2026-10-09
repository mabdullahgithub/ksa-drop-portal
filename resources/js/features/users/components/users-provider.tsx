import React, { useMemo, useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type User } from '../data/schema'

type UsersDialogType = 'invite' | 'add' | 'edit' | 'delete'

type UsersContextType = {
  open: UsersDialogType | null
  setOpen: (str: UsersDialogType | null) => void
  currentRow: User | null
  setCurrentRow: React.Dispatch<React.SetStateAction<User | null>>
  /** The ids of the users on the portal right now. */
  online: Set<number>
}

const UsersContext = React.createContext<UsersContextType | null>(null)

export function UsersProvider({
  children,
  online: onlineIds = [],
}: {
  children: React.ReactNode
  online?: number[]
}) {
  const [open, setOpen] = useDialogState<UsersDialogType>(null)
  const [currentRow, setCurrentRow] = useState<User | null>(null)
  const online = useMemo(() => new Set(onlineIds), [onlineIds])

  return (
    <UsersContext value={{ open, setOpen, currentRow, setCurrentRow, online }}>
      {children}
    </UsersContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useUsers = () => {
  const usersContext = React.useContext(UsersContext)

  if (!usersContext) {
    throw new Error('useUsers has to be used within <UsersContext>')
  }

  return usersContext
}
