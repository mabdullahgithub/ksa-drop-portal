import { type Row } from '@tanstack/react-table'
import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { Eye, Pencil, Trash2, UserCheck, UserX, Ban, MonitorSmartphone, PackagePlus } from 'lucide-react'
import { toast } from 'sonner'
import { router } from '@inertiajs/react'
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

  const handleImpersonate = () => {
    if (!client.user_id) {
      toast.error('This client has no associated user account.')
      return
    }
    router.post(route('impersonate.start', { client: client.id }))
  }

  const handleStatusUpdate = async (status: string) => {
    const success = await updateStatus(client.id, status)
    if (success) {
      toast.success('Client status updated')
      window.location.reload()
    } else {
      toast.error('Failed to update status')
    }
  }

  const showPortalGroup = (can('impersonate client') && client.status === 'active') || client.is_fulfilment

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'>
          <DotsHorizontalIcon className='h-4 w-4' />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-48'>
        {/* Group 1: View / Edit */}
        <DropdownMenuItem onClick={() => router.visit(`/client/${client.id}`)}>
          <Eye className='mr-2 h-4 w-4' />
          View Details
        </DropdownMenuItem>

        {can('edit client') && (
          <DropdownMenuItem
            onClick={() => {
              setCurrentRow(client)
              setOpen('edit')
            }}
          >
            <Pencil className='mr-2 h-4 w-4' />
            Edit Details
          </DropdownMenuItem>
        )}

        {/* Group 2: Portal / Inventory */}
        {showPortalGroup && <DropdownMenuSeparator />}

        {can('impersonate client') && client.status === 'active' && (
          <DropdownMenuItem
            onClick={handleImpersonate}
            className='text-amber-600 focus:text-amber-600 focus:bg-amber-50 dark:text-amber-400 dark:focus:text-amber-400 dark:focus:bg-amber-950/40'
          >
            <MonitorSmartphone className='mr-2 h-4 w-4' />
            View Client Portal
          </DropdownMenuItem>
        )}

        {client.is_fulfilment && (
          <DropdownMenuItem onClick={() => router.visit(`/client/${client.id}?tab=inventory`)}>
            <PackagePlus className='mr-2 h-4 w-4' />
            Add Inventory
          </DropdownMenuItem>
        )}

        {/* Group 3: Status / Delete */}
        {can('edit client') && (
          <>
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
