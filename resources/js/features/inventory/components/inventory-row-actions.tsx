import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { Eye, Pencil, CheckCircle, FileText, Archive, Globe, GlobeLock, Trash2 } from 'lucide-react'
import { useState } from 'react'
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
import { type Product } from '@/types/product'
import { useInventoryContext } from './inventory-provider'
import { useProductMutations } from '@/hooks/useProducts'
import { usePermissions } from '@/hooks/use-permissions'
import { EditProductDialog } from './edit-product-dialog'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { toast } from 'sonner'

interface ProductActionsProps {
  product: Product
}

export function ProductActions({ product }: ProductActionsProps) {
  const { setOpen, setCurrentRow } = useInventoryContext()
  const { updateProduct, deleteProduct } = useProductMutations()
  const { can } = usePermissions()
  const [showEdit, setShowEdit] = useState(false)
  const [showDelete, setShowDelete] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const canView = can('view inventory')
  const canEdit = can('edit inventory')
  const canDelete = can('delete inventory')

  const handleStatusChange = async (status: 'active' | 'draft' | 'archived') => {
    const success = await updateProduct(product.id, { status })
    if (success) {
      toast.success(`Status changed to ${status}`)
      window.location.reload()
    } else {
      toast.error('Failed to update status')
    }
  }

  const handleTogglePublished = async () => {
    const success = await updateProduct(product.id, { published: !product.published })
    if (success) {
      toast.success(product.published ? 'Product unpublished' : 'Product published')
      window.location.reload()
    } else {
      toast.error('Failed to update product')
    }
  }

  const handleDelete = async () => {
    setDeleting(true)
    try {
      const success = await deleteProduct(product.id)
      if (success) {
        toast.success('Product moved to the recycle bin')
        setShowDelete(false)
        window.location.reload()
      } else {
        toast.error('Failed to delete product')
      }
    } finally {
      setDeleting(false)
    }
  }

  if (!canView) return null

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button variant='ghost' className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'>
            <DotsHorizontalIcon className='h-4 w-4' />
            <span className='sr-only'>Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align='end' className='w-48'>
          <DropdownMenuItem
            onClick={() => {
              setCurrentRow(product)
              setOpen('view')
            }}
          >
            <Eye className='mr-2 h-4 w-4' />
            View Details
          </DropdownMenuItem>

          {canEdit && (
            <>
              <DropdownMenuSeparator />

              <DropdownMenuItem onClick={() => setShowEdit(true)}>
                <Pencil className='mr-2 h-4 w-4' />
                Edit Product
              </DropdownMenuItem>

              <DropdownMenuSub>
                <DropdownMenuSubTrigger>
                  <CheckCircle className='mr-2 h-4 w-4' />
                  Change Status
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent>
                  <DropdownMenuItem
                    onClick={() => handleStatusChange('active')}
                    disabled={product.status === 'active'}
                  >
                    <CheckCircle className='mr-2 h-3.5 w-3.5 text-green-500' />
                    Active
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => handleStatusChange('draft')}
                    disabled={product.status === 'draft'}
                  >
                    <FileText className='mr-2 h-3.5 w-3.5 text-yellow-500' />
                    Draft
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => handleStatusChange('archived')}
                    disabled={product.status === 'archived'}
                  >
                    <Archive className='mr-2 h-3.5 w-3.5 text-muted-foreground' />
                    Archived
                  </DropdownMenuItem>
                </DropdownMenuSubContent>
              </DropdownMenuSub>

              <DropdownMenuItem onClick={handleTogglePublished}>
                {product.published ? (
                  <>
                    <GlobeLock className='mr-2 h-4 w-4' />
                    Unpublish
                  </>
                ) : (
                  <>
                    <Globe className='mr-2 h-4 w-4' />
                    Publish
                  </>
                )}
              </DropdownMenuItem>
            </>
          )}

          {canDelete && (
            <>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={() => setShowDelete(true)}
                className='text-destructive focus:text-destructive'
              >
                <Trash2 className='mr-2 h-4 w-4' />
                Delete Product
              </DropdownMenuItem>
            </>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      <ConfirmDialog
        open={showDelete}
        onOpenChange={setShowDelete}
        title='Delete product'
        desc={
          <span>
            Move <strong>{product.title}</strong> to the recycle bin? You can restore it from there.
          </span>
        }
        confirmText='Delete'
        destructive
        isLoading={deleting}
        handleConfirm={handleDelete}
      />

      {showEdit && (
        <EditProductDialog
          product={product}
          open={showEdit}
          onOpenChange={setShowEdit}
        />
      )}
    </>
  )
}

type Props<TData> = { row: Row<TData> }

export function InventoryRowActions<TData>({ row }: Props<TData>) {
  return <ProductActions product={row.original as Product} />
}
