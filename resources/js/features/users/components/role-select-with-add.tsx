import { useState } from 'react'
import { Plus } from 'lucide-react'
import { cn } from '@/lib/utils'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  SelectSeparator,
} from '@/components/ui/select'
import { CreateRoleDialog } from './create-role-dialog'

type RoleSelectWithAddProps = {
  onValueChange?: (value: string) => void
  value: string | undefined
  placeholder?: string
  items: { label: string; value: string }[]
  disabled?: boolean
  className?: string
  availablePermissions?: string[]
}

export function RoleSelectWithAdd({
  value,
  onValueChange,
  items,
  placeholder,
  disabled,
  className = '',
  availablePermissions = [],
}: RoleSelectWithAddProps) {
  const [showCreateDialog, setShowCreateDialog] = useState(false)
  const [selectOpen, setSelectOpen] = useState(false)

  const handleSelectChange = (selectedValue: string) => {
    if (selectedValue === '__add_new__') {
      setSelectOpen(false)
      setShowCreateDialog(true)
    } else {
      onValueChange?.(selectedValue)
    }
  }

  const handleRoleCreated = (newRoleName: string) => {
    setShowCreateDialog(false)
    // Set the newly created role as selected
    onValueChange?.(newRoleName)
  }

  return (
    <>
      <Select value={value} onValueChange={handleSelectChange} open={selectOpen} onOpenChange={setSelectOpen}>
        <FormControl>
          <SelectTrigger disabled={disabled} className={cn(className)}>
            <SelectValue placeholder={placeholder ?? 'Select a role'} />
          </SelectTrigger>
        </FormControl>
        <SelectContent>
          {items?.map(({ label, value }) => (
            <SelectItem key={value} value={value}>
              {label}
            </SelectItem>
          ))}

          <SelectSeparator />

          <SelectItem
            value="__add_new__"
            className="text-primary font-medium cursor-pointer"
          >
            <div className="flex items-center gap-2">
              <Plus className="h-4 w-4" />
              <span>Add New Role</span>
            </div>
          </SelectItem>
        </SelectContent>
      </Select>

      <CreateRoleDialog
        open={showCreateDialog}
        onOpenChange={setShowCreateDialog}
        onRoleCreated={handleRoleCreated}
        availablePermissions={availablePermissions}
      />
    </>
  )
}
