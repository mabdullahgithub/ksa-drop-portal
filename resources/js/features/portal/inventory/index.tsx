import { useState } from 'react'
import { toast } from 'sonner'
import { Search as SearchIcon, CheckCircle, Clock, Plus, Lock, Pencil, Trash2, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
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
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalInventory, usePortalInventoryMutations } from '@/hooks/usePortal'
import { PortalProductFormDialog } from './portal-product-form-dialog'

interface PortalProduct {
  id: number
  product_code: string
  name: string
  sku: string | null
  description: string | null
  quantity: number
  unit_price: string | null
  notes: string | null
  verification_status: 'pending' | 'verified'
  created_at: string
}

type SortField = 'product_code' | 'quantity' | 'created_at'
type SortOrder = 'asc' | 'desc'

export function PortalInventory() {
  const { products, meta, loading, updateFilters, refresh } = usePortalInventory()
  const { loading: mutating, deleteProduct } = usePortalInventoryMutations()

  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [sortBy, setSortBy] = useState<SortField>('created_at')
  const [sortOrder, setSortOrder] = useState<SortOrder>('desc')
  const [formOpen, setFormOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState<PortalProduct | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<PortalProduct | null>(null)

  const handleSearch = (value: string) => {
    setSearch(value)
    updateFilters({ search: value, page: 1 })
  }

  const handleStatusFilter = (value: string) => {
    setStatusFilter(value)
    updateFilters({ verification_status: value, page: 1 })
  }

  const handleSort = (field: SortField) => {
    const newOrder: SortOrder = sortBy === field && sortOrder === 'asc' ? 'desc' : 'asc'
    setSortBy(field)
    setSortOrder(newOrder)
    updateFilters({ sort_by: field, sort_order: newOrder, page: 1 })
  }

  const SortIcon = ({ field }: { field: SortField }) => {
    if (sortBy !== field) return <ArrowUpDown className='ml-1 inline h-3.5 w-3.5 text-muted-foreground/60' />
    return sortOrder === 'asc'
      ? <ArrowUp className='ml-1 inline h-3.5 w-3.5' />
      : <ArrowDown className='ml-1 inline h-3.5 w-3.5' />
  }

  const openAdd = () => {
    setEditingProduct(null)
    setFormOpen(true)
  }

  const openEdit = (product: PortalProduct) => {
    setEditingProduct(product)
    setFormOpen(true)
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    const result = await deleteProduct(deleteTarget.id)
    if (result === true) {
      toast.success(`"${deleteTarget.name}" removed from inventory`)
      setDeleteTarget(null)
      refresh()
    } else if (result && typeof result === 'object' && 'message' in result) {
      toast.error((result as { message: string }).message)
      setDeleteTarget(null)
    } else {
      toast.error('Failed to delete product')
    }
  }

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ConfigDrawer />
        <ProfileDropdown />
      </Header>

      <Main fixed>
        <div className='mb-4 flex items-start justify-between'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>My Inventory</h1>
            <p className='text-muted-foreground'>Manage your products and track verification status</p>
          </div>
          <Button onClick={openAdd}>
            <Plus className='mr-2 h-4 w-4' />
            Add Product
          </Button>
        </div>

        <div className='space-y-4'>
          <div className='flex flex-wrap items-center gap-2'>
            <div className='relative'>
              <SearchIcon className='absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground' />
              <Input
                placeholder='Search products...'
                value={search}
                onChange={(e) => handleSearch(e.target.value)}
                className='pl-8 w-56'
              />
            </div>
            <Select value={statusFilter} onValueChange={handleStatusFilter}>
              <SelectTrigger className='w-36'>
                <SelectValue placeholder='Status' />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value='all'>All Statuses</SelectItem>
                <SelectItem value='pending'>Pending</SelectItem>
                <SelectItem value='verified'>Verified</SelectItem>
              </SelectContent>
            </Select>
            <Select
              value={`${sortBy}:${sortOrder}`}
              onValueChange={(v) => {
                const [field, order] = v.split(':') as [SortField, SortOrder]
                setSortBy(field)
                setSortOrder(order)
                updateFilters({ sort_by: field, sort_order: order, page: 1 })
              }}
            >
              <SelectTrigger className='w-44'>
                <SelectValue placeholder='Sort by' />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value='product_code:asc'>Code — A to Z</SelectItem>
                <SelectItem value='product_code:desc'>Code — Z to A</SelectItem>
                <SelectItem value='quantity:asc'>Quantity — Low to High</SelectItem>
                <SelectItem value='quantity:desc'>Quantity — High to Low</SelectItem>
                <SelectItem value='created_at:desc'>Date Added — Newest</SelectItem>
                <SelectItem value='created_at:asc'>Date Added — Oldest</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className='overflow-hidden rounded-md border'>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>
                    <button onClick={() => handleSort('product_code')} className='flex items-center font-medium hover:text-foreground'>
                      Code<SortIcon field='product_code' />
                    </button>
                  </TableHead>
                  <TableHead>Product Name</TableHead>
                  <TableHead>SKU</TableHead>
                  <TableHead>
                    <button onClick={() => handleSort('quantity')} className='flex items-center font-medium hover:text-foreground'>
                      Quantity<SortIcon field='quantity' />
                    </button>
                  </TableHead>
                  <TableHead>Unit Price</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>
                    <button onClick={() => handleSort('created_at')} className='flex items-center font-medium hover:text-foreground'>
                      Added<SortIcon field='created_at' />
                    </button>
                  </TableHead>
                  <TableHead className='w-10' />
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={i}>
                      {Array.from({ length: 8 }).map((_, j) => (
                        <TableCell key={j}>
                          <div className='h-4 w-full animate-pulse rounded bg-muted' />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : (products as PortalProduct[]).length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className='h-32 text-center'>
                      <div className='flex flex-col items-center gap-2 text-muted-foreground'>
                        <p className='text-sm'>You haven&apos;t added any products yet.</p>
                        <p className='text-xs'>Add your first product to request warehouse intake.</p>
                        <Button variant='outline' size='sm' className='mt-2' onClick={openAdd}>
                          <Plus className='mr-2 h-4 w-4' />
                          Add Product
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ) : (
                  (products as PortalProduct[]).map((product) => {
                    const isPending = product.verification_status === 'pending'
                    return (
                      <TableRow key={product.id}>
                        <TableCell>
                          <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold'>
                            {product.product_code}
                          </code>
                        </TableCell>
                        <TableCell className='font-medium'>{product.name}</TableCell>
                        <TableCell className='text-sm text-muted-foreground'>
                          {product.sku || '—'}
                        </TableCell>
                        <TableCell>{product.quantity}</TableCell>
                        <TableCell className='text-sm'>
                          {product.unit_price
                            ? `SAR ${parseFloat(product.unit_price).toFixed(2)}`
                            : '—'}
                        </TableCell>
                        <TableCell>
                          {isPending ? (
                            <Badge
                              variant='outline'
                              className='bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 text-xs'
                              title='Awaiting admin verification'
                            >
                              <Clock className='mr-1 h-3 w-3' />
                              Pending
                            </Badge>
                          ) : (
                            <Badge
                              variant='outline'
                              className='bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 text-xs'
                            >
                              <CheckCircle className='mr-1 h-3 w-3' />
                              Verified
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className='text-sm text-muted-foreground'>
                          {new Date(product.created_at).toLocaleDateString()}
                        </TableCell>
                        <TableCell>
                          {isPending ? (
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
                              <DropdownMenuContent align='end' className='w-40'>
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
                          ) : (
                            <div className='flex h-8 w-8 items-center justify-center' title='Verified products are locked'>
                              <Lock className='h-3.5 w-3.5 text-muted-foreground/50' />
                            </div>
                          )}
                        </TableCell>
                      </TableRow>
                    )
                  })
                )}
              </TableBody>
            </Table>
          </div>

          {meta && meta.total > 0 && (
            <div className='text-sm text-muted-foreground'>
              Showing {meta.from} to {meta.to} of {meta.total} products
            </div>
          )}
        </div>
      </Main>

      <PortalProductFormDialog
        product={editingProduct}
        open={formOpen}
        onOpenChange={setFormOpen}
        onSuccess={refresh}
      />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => { if (!open) setDeleteTarget(null) }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remove Product</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to remove <strong>{deleteTarget?.name}</strong> from your inventory? This cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={mutating}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={mutating}
              className='bg-destructive text-destructive-foreground hover:bg-destructive/90'
            >
              {mutating ? 'Removing...' : 'Remove'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
