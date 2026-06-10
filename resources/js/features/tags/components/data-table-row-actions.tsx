import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { usePermissions } from '@/hooks/use-permissions'
import { type Tag } from '../data/schema'
import { useTags } from './tags-provider'

export function DataTableRowActions({ row }: { row: Row<Tag> }) {
  const { setOpen, setCurrentRow } = useTags()
  const { can } = usePermissions()

  const canEdit   = can('edit tags')
  const canDelete = can('delete tags')

  if (!canEdit && !canDelete) return null

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'>
          <DotsHorizontalIcon className='h-4 w-4' />
          <span className='sr-only'>Open menu</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-36'>
        {canEdit && (
          <DropdownMenuItem
            onClick={() => { setCurrentRow(row.original); setOpen('edit') }}
          >
            Edit
            <DropdownMenuShortcut><Pencil size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
        {canEdit && canDelete && <DropdownMenuSeparator />}
        {canDelete && (
          <DropdownMenuItem
            className='text-red-500!'
            onClick={() => { setCurrentRow(row.original); setOpen('delete') }}
          >
            Delete
            <DropdownMenuShortcut><Trash2 size={16} /></DropdownMenuShortcut>
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
