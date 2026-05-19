import { type Table } from '@tanstack/react-table'
import { CheckCircle, FileText, Archive, Globe, GlobeLock } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DataTableBulkActions as BulkActionsToolbar } from '@/components/data-table'
import { type Product } from '@/types/product'
import { useProductMutations } from '@/hooks/useProducts'

type InventoryBulkActionsProps<TData> = {
  table: Table<TData>
}

export function InventoryBulkActions<TData>({ table }: InventoryBulkActionsProps<TData>) {
  const selectedRows = table.getFilteredSelectedRowModel().rows
  const { updateProduct } = useProductMutations()

  const handleBulkStatusChange = async (status: 'active' | 'draft' | 'archived') => {
    const products = selectedRows.map((row) => row.original as Product)
    const label = status.charAt(0).toUpperCase() + status.slice(1)

    toast.promise(
      Promise.all(products.map((p) => updateProduct(p.id, { status }))),
      {
        loading: `Setting ${products.length} product${products.length > 1 ? 's' : ''} to ${label}…`,
        success: () => {
          table.resetRowSelection()
          window.location.reload()
          return `${products.length} product${products.length > 1 ? 's' : ''} set to ${label}`
        },
        error: 'Failed to update some products',
      }
    )
  }

  const handleBulkPublish = async (published: boolean) => {
    const products = selectedRows.map((row) => row.original as Product)
    const label = published ? 'published' : 'unpublished'

    toast.promise(
      Promise.all(products.map((p) => updateProduct(p.id, { published }))),
      {
        loading: `${published ? 'Publishing' : 'Unpublishing'} ${products.length} product${products.length > 1 ? 's' : ''}…`,
        success: () => {
          table.resetRowSelection()
          window.location.reload()
          return `${products.length} product${products.length > 1 ? 's' : ''} ${label}`
        },
        error: `Failed to ${label} some products`,
      }
    )
  }

  return (
    <BulkActionsToolbar table={table} entityName='product'>
      {/* Change Status */}
      <DropdownMenu>
        <Tooltip>
          <TooltipTrigger asChild>
            <DropdownMenuTrigger asChild>
              <Button variant='outline' size='icon' className='size-8' aria-label='Change status'>
                <CheckCircle className='h-4 w-4' />
                <span className='sr-only'>Change status</span>
              </Button>
            </DropdownMenuTrigger>
          </TooltipTrigger>
          <TooltipContent>
            <p>Change status</p>
          </TooltipContent>
        </Tooltip>
        <DropdownMenuContent sideOffset={14}>
          <DropdownMenuItem onClick={() => handleBulkStatusChange('active')}>
            <CheckCircle className='mr-2 h-3.5 w-3.5 text-green-500' />
            Set Active
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => handleBulkStatusChange('draft')}>
            <FileText className='mr-2 h-3.5 w-3.5 text-yellow-500' />
            Set Draft
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => handleBulkStatusChange('archived')}>
            <Archive className='mr-2 h-3.5 w-3.5 text-muted-foreground' />
            Set Archived
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      {/* Publish */}
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant='outline'
            size='icon'
            className='size-8'
            aria-label='Publish selected'
            onClick={() => handleBulkPublish(true)}
          >
            <Globe className='h-4 w-4' />
            <span className='sr-only'>Publish selected</span>
          </Button>
        </TooltipTrigger>
        <TooltipContent>
          <p>Publish selected</p>
        </TooltipContent>
      </Tooltip>

      {/* Unpublish */}
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant='outline'
            size='icon'
            className='size-8'
            aria-label='Unpublish selected'
            onClick={() => handleBulkPublish(false)}
          >
            <GlobeLock className='h-4 w-4' />
            <span className='sr-only'>Unpublish selected</span>
          </Button>
        </TooltipTrigger>
        <TooltipContent>
          <p>Unpublish selected</p>
        </TooltipContent>
      </Tooltip>
    </BulkActionsToolbar>
  )
}
