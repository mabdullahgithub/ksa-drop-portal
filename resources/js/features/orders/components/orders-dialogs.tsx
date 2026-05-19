import { showSubmittedData } from '@/lib/show-submitted-data'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { OrdersImportDialog } from './orders-import-dialog'
import { OrdersMutateDrawer } from './orders-mutate-drawer'
import { useOrders } from './orders-provider'

export function OrdersDialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useOrders()
  return (
    <>
      <OrdersMutateDrawer
        key='order-create'
        open={open === 'create'}
        onOpenChange={() => setOpen('create')}
      />

      <OrdersImportDialog
        key='orders-import'
        open={open === 'import'}
        onOpenChange={() => setOpen('import')}
      />

      {currentRow && (
        <>
          <OrdersMutateDrawer
            key={`order-update-${currentRow.id}`}
            open={open === 'update'}
            onOpenChange={() => {
              setOpen('update')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
          />

          <ConfirmDialog
            key='order-delete'
            destructive
            open={open === 'delete'}
            onOpenChange={() => {
              setOpen('delete')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            handleConfirm={() => {
              setOpen(null)
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
              showSubmittedData(
                currentRow,
                'The following order has been deleted:'
              )
            }}
            className='max-w-md'
            title={`Delete this order: ${currentRow.id} ?`}
            desc={
              <>
                You are about to delete an order with the ID{' '}
                <strong>{currentRow.id}</strong>. <br />
                This action cannot be undone.
              </>
            }
            confirmText='Delete'
          />
        </>
      )}
    </>
  )
}
