import { OrderDetailsDialog } from './order-details-dialog'
import { CreateShipmentDialog } from './create-shipment-dialog'
import { useOrdersContext } from './orders-provider'

interface OrdersDialogsProps {
  onSuccess?: () => void
}

export function OrdersDialogs({ onSuccess }: OrdersDialogsProps) {
  const { open, setOpen, currentRow, setCurrentRow } = useOrdersContext()

  return (
    <>
      {currentRow && (
        <OrderDetailsDialog
          order={currentRow}
          open={open === 'view'}
          onOpenChange={(isOpen) => {
            if (!isOpen) {
              setOpen(null)
              setTimeout(() => {
                setCurrentRow(null)
              }, 300)
            }
          }}
        />
      )}

      {currentRow && (
        <CreateShipmentDialog
          order={currentRow}
          open={open === 'shipment'}
          onOpenChange={(isOpen) => {
            if (!isOpen) {
              setOpen(null)
              setTimeout(() => {
                setCurrentRow(null)
              }, 300)
            }
          }}
          onSuccess={onSuccess}
        />
      )}
    </>
  )
}
