import {
  ChevronLeftIcon,
  ChevronRightIcon,
  DoubleArrowLeftIcon,
  DoubleArrowRightIcon,
} from '@radix-ui/react-icons'
import { getPageNumbers } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { type RecycleBinMeta } from '@/hooks/useRecycleBin'

type RecycleBinPaginationProps = {
  meta: RecycleBinMeta
  onPageChange: (page: number) => void
  onPageSizeChange: (size: number) => void
}

export function RecycleBinPagination({ meta, onPageChange, onPageSizeChange }: RecycleBinPaginationProps) {
  const currentPage = meta.current_page
  const totalPages = meta.last_page
  const pageNumbers = getPageNumbers(currentPage, totalPages)

  const canPreviousPage = currentPage > 1
  const canNextPage = currentPage < totalPages

  return (
    <div className='flex flex-wrap items-center justify-between gap-4 px-2'>
      <div className='flex items-center gap-2'>
        <Select value={`${meta.per_page}`} onValueChange={(value) => onPageSizeChange(Number(value))}>
          <SelectTrigger className='h-8 w-17.5'>
            <SelectValue placeholder={meta.per_page} />
          </SelectTrigger>
          <SelectContent side='top'>
            {[10, 25, 50, 100].map((size) => (
              <SelectItem key={size} value={`${size}`}>{size}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <p className='hidden text-sm font-medium sm:block'>Rows per page</p>
        <p className='text-muted-foreground text-sm'>({meta.total} total)</p>
      </div>

      <div className='flex items-center gap-4'>
        <div className='text-sm font-medium'>Page {currentPage} of {totalPages}</div>
        <div className='flex items-center space-x-2'>
          <Button variant='outline' className='size-8 p-0' onClick={() => onPageChange(1)} disabled={!canPreviousPage}>
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
          <Button variant='outline' className='size-8 p-0' onClick={() => onPageChange(totalPages)} disabled={!canNextPage}>
            <DoubleArrowRightIcon className='h-4 w-4' />
          </Button>
        </div>
      </div>
    </div>
  )
}
