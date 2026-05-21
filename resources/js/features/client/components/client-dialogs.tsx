import { toast } from 'sonner'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { useClientMutations } from '@/hooks/useClients'
import { useClientContext } from './client-provider'
import { ClientDetailsDialog } from './client-details-dialog'
import { CreateClientDialog } from './create-client-dialog'
import { EditClientDialog } from './edit-client-dialog'

interface ClientDialogsProps {
  onSuccess: () => void
}

export function ClientDialogs({ onSuccess }: ClientDialogsProps) {
  const { open, setOpen, currentRow, setCurrentRow } = useClientContext()
  const { deleteClient, loading } = useClientMutations()

  const handleClose = () => {
    setOpen(null)
    setTimeout(() => setCurrentRow(null), 300)
  }

  const handleDelete = async () => {
    if (!currentRow) return
    const success = await deleteClient(currentRow.id)
    if (success) {
      toast.success('Client deleted successfully')
      handleClose()
      onSuccess()
    } else {
      toast.error('Failed to delete client')
    }
  }

  return (
    <>
      <CreateClientDialog
        open={open === 'create'}
        onOpenChange={(isOpen) => { if (!isOpen) handleClose() }}
        onSuccess={onSuccess}
      />

      {currentRow && (
        <>
          <ClientDetailsDialog
            client={currentRow}
            open={open === 'view'}
            onOpenChange={(isOpen) => { if (!isOpen) handleClose() }}
          />

          <EditClientDialog
            client={currentRow}
            open={open === 'edit'}
            onOpenChange={(isOpen) => { if (!isOpen) handleClose() }}
            onSuccess={onSuccess}
          />

          <AlertDialog open={open === 'delete'} onOpenChange={(isOpen) => { if (!isOpen) handleClose() }}>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Delete Client</AlertDialogTitle>
                <AlertDialogDescription>
                  Are you sure you want to delete <strong>{currentRow.company_name}</strong>? This action cannot be undone. The associated user account will remain but will lose client access.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction onClick={handleDelete} disabled={loading} className='bg-destructive text-destructive-foreground hover:bg-destructive/90'>
                  {loading ? 'Deleting...' : 'Delete'}
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </>
      )}
    </>
  )
}
