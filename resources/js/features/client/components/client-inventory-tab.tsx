import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, CheckCircle, Clock, ShieldCheck, Pencil, Trash2, AlertTriangle, RefreshCw } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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
import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { usePermissions } from '@/hooks/use-permissions'
import { useClientProducts, useClientProductMutations } from '@/hooks/useClientProducts'
import { ClientProductFormDialog } from './client-product-form-dialog'
import type { Client, ClientProduct } from '@/types/client'

interface ClientInventoryTabProps {
  client: Client
}

export function ClientInventoryTab({ client }: ClientInventoryTabProps) {
  const { can } = usePermissions()
  // Client product routes are gated on 'edit client' / 'delete client' — match that here
  const canEdit = can('edit client')
  const { products, loading, error, refresh } = useClientProducts(client.id)
  const { loading: mutating, verifyProduct, deleteProduct } = useClientProductMutations()

  const [formOpen, setFormOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState<ClientProduct | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<ClientProduct | null>(null)

  const handleVerify = async (product: ClientProduct) => {
    const ok = await verifyProduct(client.id, product.id)
    if (ok) {
      toast.success(`"${product.name}" verified`)
      refresh()
    } else {
      toast.error('Failed to verify product')
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    const ok = await deleteProduct(client.id, deleteTarget.id)
    if (ok) {
      toast.success(`"${deleteTarget.name}" deleted`)
      setDeleteTarget(null)
      refresh()
    } else {
      toast.error('Failed to delete product')
    }
  }

  const openAdd = () => {
    setEditingProduct(null)
    setFormOpen(true)
  }

  const openEdit = (product: ClientProduct) => {
    setEditingProduct(product)
    setFormOpen(true)
  }

  if (error) {
    return (
      <div className='flex flex-col items-center justify-center py-12 text-center gap-3'>
        <AlertTriangle className='h-8 w-8 text-muted-foreground' />
        <p className='text-sm text-muted-foreground'>Failed to load products.</p>
        <Button variant='outline' size='sm' onClick={refresh}>
          <RefreshCw className='mr-2 h-4 w-4' />
          Try Again
        </Button>
      </div>
    )
  }

  return (
    <div className='space-y-4'>
      <div className='flex items-center justify-between'>
        <p className='text-sm text-muted-foreground'>
          {loading ? 'Loading...' : `${products.length} product${products.length !== 1 ? 's' : ''} in inventory`}
        </p>
        {canEdit && (
          <Button size='sm' onClick={openAdd}>
            <Plus className='mr-2 h-4 w-4' />
            Add Product
          </Button>
        )}
      </div>

      <div className='rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Code</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead>Qty</TableHead>
              <TableHead>Unit Price</TableHead>
              <TableHead>Status</TableHead>
              {canEdit && <TableHead className='w-10' />}
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                  {[...Array(canEdit ? 7 : 6)].map((_, j) => (
                    <TableCell key={j}>
                      <div className='h-4 w-full animate-pulse rounded bg-muted' />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : products.length === 0 ? (
              <TableRow>
                <TableCell colSpan={canEdit ? 7 : 6} className='h-24 text-center text-muted-foreground'>
                  No products in inventory.
                  {canEdit && (
                    <Button variant='link' className='ml-1 h-auto p-0' onClick={openAdd}>
                      Add the first one.
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ) : (
              products.map((product) => (
                <TableRow key={product.id}>
                  <TableCell>
                    <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold'>
                      {product.product_code}
                    </code>
                  </TableCell>
                  <TableCell className='font-medium'>{product.name}</TableCell>
                  <TableCell className='text-sm text-muted-foreground'>{product.sku || '—'}</TableCell>
                  <TableCell>{product.quantity}</TableCell>
                  <TableCell className='text-sm'>
                    {product.unit_price ? `SAR ${parseFloat(product.unit_price).toFixed(2)}` : '—'}
                  </TableCell>
                  <TableCell>
                    {product.verification_status === 'verified' ? (
                      <Badge variant='outline' className='bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 text-xs'>
                        <CheckCircle className='mr-1 h-3 w-3' />
                        Verified
                      </Badge>
                    ) : (
                      <Badge variant='outline' className='bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 text-xs'>
                        <Clock className='mr-1 h-3 w-3' />
                        Pending
                      </Badge>
                    )}
                  </TableCell>
                  {canEdit && (
                    <TableCell>
                      <DropdownMenu modal={false}>
                        <DropdownMenuTrigger asChild>
                          <Button
                            variant='ghost'
                            className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
                            disabled={mutating}
                          >
                            <DotsHorizontalIcon className='h-4 w-4' />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align='end' className='w-44'>
                          {product.verification_status === 'pending' && (
                            <DropdownMenuItem onClick={() => handleVerify(product)}>
                              <ShieldCheck className='mr-2 h-4 w-4' />
                              Verify
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuItem onClick={() => openEdit(product)}>
                            <Pencil className='mr-2 h-4 w-4' />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem
                            className='text-destructive'
                            onClick={() => setDeleteTarget(product)}
                          >
                            <Trash2 className='mr-2 h-4 w-4' />
                            Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  )}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <ClientProductFormDialog
        clientId={client.id}
        product={editingProduct}
        open={formOpen}
        onOpenChange={setFormOpen}
        onSuccess={refresh}
      />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => { if (!open) setDeleteTarget(null) }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Product</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete <strong>{deleteTarget?.name}</strong>? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={mutating}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={mutating}
              className='bg-destructive text-destructive-foreground hover:bg-destructive/90'
            >
              {mutating ? 'Deleting...' : 'Delete'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
