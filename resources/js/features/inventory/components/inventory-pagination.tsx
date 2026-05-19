import {
  ChevronLeftIcon,
  ChevronRightIcon,
  DoubleArrowLeftIcon,
  DoubleArrowRightIcon,
} from '@radix-ui/react-icons'
import { cn, getPageNumbers } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

type InventoryPaginationProps = {
  meta: {
    current_page: number
    total: number
    per_page: number
    last_page: number
    from: number
    to: number
  }
  onPageChange: (page: number) => void
  onPageSizeChange?: (pageSize: number) => void
  className?: string
}

export function InventoryPagination({ meta, onPageChange, onPageSizeChange, className }: InventoryPaginationProps) {
  const currentPage = meta.current_page
  const totalPages = meta.last_page
  const pageNumbers = getPageNumbers(currentPage, totalPages)

  const canPreviousPage = currentPage > 1
  const canNextPage = currentPage < totalPages

  return (
    <div
      className={cn('flex items-center justify-between overflow-clip px-2', '@max-2xl/content:flex-col-reverse @max-2xl/content:gap-4', className)}
      style={{ overflowClipMargin: 1 }}
    >
      <div className='flex w-full items-center justify-between'>
        <div className='flex w-25 items-center justify-center text-sm font-medium @2xl/content:hidden'>
          Page {currentPage} of {totalPages}
        </div>
        <div className='flex items-center gap-2 @max-2xl/content:flex-row-reverse'>
          {onPageSizeChange && (
            <>
              <Select value={`${meta.per_page}`} onValueChange={(v) => onPageSizeChange(Number(v))}>
                <SelectTrigger className='h-8 w-17.5'>
                  <SelectValue placeholder={meta.per_page} />
                </SelectTrigger>
                <SelectContent side='top'>
                  {[10, 15, 20, 30, 50].map((size) => (
                    <SelectItem key={size} value={`${size}`}>{size}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className='hidden text-sm font-medium sm:block'>Rows per page</p>
            </>
          )}
        </div>
      </div>

      <div className='flex items-center sm:space-x-6 lg:space-x-8'>
        <div className='flex w-25 items-center justify-center text-sm font-medium @max-3xl/content:hidden'>
          Page {currentPage} of {totalPages}
        </div>
        <div className='flex items-center space-x-2'>
          <Button variant='outline' className='size-8 p-0 @max-md/content:hidden' onClick={() => onPageChange(1)} disabled={!canPreviousPage}>
            <DoubleArrowLeftIcon className='h-4 w-4' />
          </Button>
          <Button variant='outline' className='size-8 p-0' onClick={() => onPageChange(currentPage - 1)} disabled={!canPreviousPage}>
            <ChevronLeftIcon className='h-4 w-4' />
          </Button>
          {pageNumbers.map((pageNumber, index) => (
            <div key={`${pageNumber}-${index}`} className='flex items-center'>
              {pageNumber === '...' ? (
                <span className='px-1 text-sm text-muted-foreground'>...</span>
              ) : (
                <Button
                  variant={currentPage === pageNumber ? 'default' : 'outline'}
                  className='h-8 min-w-8 px-2'
                  onClick={() => onPageChange(pageNumber as number)}
                >
                  {pageNumber}
                </Button>
              )}
            </div>
          ))}
          <Button variant='outline' className='size-8 p-0' onClick={() => onPageChange(currentPage + 1)} disabled={!canNextPage}>
            <ChevronRightIcon className='h-4 w-4' />
          </Button>
          <Button variant='outline' className='size-8 p-0 @max-md/content:hidden' onClick={() => onPageChange(totalPages)} disabled={!canNextPage}>
            <DoubleArrowRightIcon className='h-4 w-4' />
          </Button>
        </div>
      </div>
    </div>
  )
}
