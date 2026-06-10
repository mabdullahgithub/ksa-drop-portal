import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Tag } from '../data/schema'

type TagsDialogType = 'add' | 'edit' | 'delete'

type TagsContextType = {
  open: TagsDialogType | null
  setOpen: (str: TagsDialogType | null) => void
  currentRow: Tag | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Tag | null>>
}

const TagsContext = React.createContext<TagsContextType | null>(null)

export function TagsProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<TagsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Tag | null>(null)

  return (
    <TagsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </TagsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useTags = () => {
  const ctx = React.useContext(TagsContext)
  if (!ctx) throw new Error('useTags must be used within <TagsProvider>')
  return ctx
}
