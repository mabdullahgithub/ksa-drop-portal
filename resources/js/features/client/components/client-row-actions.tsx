import { type Row } from '@tanstack/react-table'
import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { Eye, Pencil, Trash2, UserCheck, UserX, Ban } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { usePermissions } from '@/hooks/use-permissions'
import { useClientMutations } from '@/hooks/useClients'
import { useClientContext } from './client-provider'
import type { Client } from '@/types/client'

interface ClientRowActionsProps {
  row: Row<Client>
}

export function ClientRowActions({ row }: ClientRowActionsProps) {
  const client = row.original
  const { setOpen, setCurrentRow } = useClientContext()
  const { can } = usePermissions()
  const { updateStatus } = useClientMutations()

  const handleStatusUpdate = async (status: string) => {
    const success = await updateStatus(client.id, status)
    if (success) {
      toast.success('Client status updated')
      window.location.reload()
    } else {
      toast.error('Failed to update status')
    }
  }

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'>
          <DotsHorizontalIcon className='h-4 w-4' />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-48'>
        <DropdownMenuItem
          onClick={() => {
            setCurrentRow(client)
            setOpen('view')
          }}
        >
          <Eye className='mr-2 h-4 w-4' />
          View Details
        </DropdownMenuItem>

        {can('edit client') && (
          <>
            <DropdownMenuItem
              onClick={() => {
                setCurrentRow(client)
                setOpen('edit')
              }}
            >
              <Pencil className='mr-2 h-4 w-4' />
              Edit
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuSub>
              <DropdownMenuSubTrigger>
                <UserCheck className='mr-2 h-4 w-4' />
                Change Status
              </DropdownMenuSubTrigger>
              <DropdownMenuSubContent>
                <DropdownMenuItem onClick={() => handleStatusUpdate('active')} disabled={client.status === 'active'}>
                  <UserCheck className='mr-2 h-4 w-4' />
                  Activate
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleStatusUpdate('inactive')} disabled={client.status === 'inactive'}>
                  <UserX className='mr-2 h-4 w-4' />
                  Deactivate
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleStatusUpdate('suspended')} disabled={client.status === 'suspended'}>
                  <Ban className='mr-2 h-4 w-4' />
                  Suspend
                </DropdownMenuItem>
              </DropdownMenuSubContent>
            </DropdownMenuSub>
          </>
        )}

        {can('delete client') && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              className='text-destructive'
              onClick={() => {
                setCurrentRow(client)
                setOpen('delete')
              }}
            >
              <Trash2 className='mr-2 h-4 w-4' />
              Delete
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
