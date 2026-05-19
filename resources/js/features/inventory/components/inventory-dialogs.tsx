import { useInventoryContext } from './inventory-provider'
import { ProductDetailsDialog } from './product-details-dialog'

export function InventoryDialogs() {
  const { open, setOpen, currentRow } = useInventoryContext()

  return (
    <>
      {currentRow && (
        <ProductDetailsDialog
          product={currentRow}
          open={open === 'view'}
          onOpenChange={(isOpen) => {
            if (!isOpen) setOpen(null)
          }}
        />
      )}
    </>
  )
}
