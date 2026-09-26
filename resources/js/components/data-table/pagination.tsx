import {
  ChevronLeftIcon,
  ChevronRightIcon,
  DoubleArrowLeftIcon,
  DoubleArrowRightIcon,
} from '@radix-ui/react-icons'
import { type Table } from '@tanstack/react-table'
import { cn, getPageNumbers } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

/**
 * The single source of truth for pagination across the app. Every paginated
 * list renders <Pagination> (server-side lists) or <DataTablePagination>
 * (client-side TanStack tables), so the look, the page-size options and the
 * default page size are identical everywhere.
 */
export const PAGE_SIZE_OPTIONS = [10, 20, 30, 50, 100]
export const DEFAULT_PAGE_SIZE = 20

/** The subset of a Laravel paginator payload the control needs. */
export type PaginationMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from?: number | null
  to?: number | null
}

type PaginationProps = {
  meta: PaginationMeta
  onPageChange: (page: number) => void
  onPageSizeChange: (pageSize: number) => void
  disabled?: boolean
  className?: string
}

export function Pagination({
  meta,
  onPageChange,
  onPageSizeChange,
  disabled = false,
  className,
}: PaginationProps) {
  if (!meta.total) return null

  const currentPage = meta.current_page
  const totalPages = Math.max(meta.last_page, 1)
  const pageNumbers = getPageNumbers(currentPage, totalPages)
  const from = meta.from ?? (currentPage - 1) * meta.per_page + 1
  const to = meta.to ?? Math.min(currentPage * meta.per_page, meta.total)

  const canPreviousPage = currentPage > 1 && !disabled
  const canNextPage = currentPage < totalPages && !disabled

  // Keep the select from rendering blank if a caller lands on an odd size.
  const pageSizes = PAGE_SIZE_OPTIONS.includes(meta.per_page)
    ? PAGE_SIZE_OPTIONS
    : [...PAGE_SIZE_OPTIONS, meta.per_page].sort((a, b) => a - b)

  return (
    <div
      className={cn(
        'flex items-center justify-between gap-4 overflow-clip px-2',
        '@max-2xl/content:flex-col-reverse',
        className
      )}
      style={{ overflowClipMargin: 1 }}
    >
      <div className='flex items-center gap-4 @max-2xl/content:w-full @max-2xl/content:justify-between'>
        <div className='flex items-center gap-2'>
          <Select
            value={`${meta.per_page}`}
            onValueChange={(value) => onPageSizeChange(Number(value))}
            disabled={disabled}
          >
            <SelectTrigger className='h-8 w-17.5'>
              <SelectValue placeholder={meta.per_page} />
            </SelectTrigger>
            <SelectContent side='top'>
              {pageSizes.map((pageSize) => (
                <SelectItem key={pageSize} value={`${pageSize}`}>
                  {pageSize}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className='hidden text-sm font-medium sm:block'>Rows per page</p>
        </div>
        <p className='text-sm whitespace-nowrap text-muted-foreground'>
          Showing {from}–{to} of {meta.total}
        </p>
      </div>

      <div className='flex items-center sm:space-x-6 lg:space-x-8'>
        <div className='flex items-center justify-center text-sm font-medium whitespace-nowrap @max-3xl/content:hidden'>
          Page {currentPage} of {totalPages}
        </div>
        <div className='flex items-center space-x-2'>
          <Button
            variant='outline'
            className='size-8 p-0 @max-md/content:hidden'
            onClick={() => onPageChange(1)}
            disabled={!canPreviousPage}
          >
            <span className='sr-only'>Go to first page</span>
            <DoubleArrowLeftIcon className='h-4 w-4' />
          </Button>
          <Button
            variant='outline'
            className='size-8 p-0'
            onClick={() => onPageChange(currentPage - 1)}
            disabled={!canPreviousPage}
          >
            <span className='sr-only'>Go to previous page</span>
            <ChevronLeftIcon className='h-4 w-4' />
          </Button>

          {/* Page number buttons */}
          {pageNumbers.map((pageNumber, index) => (
            <div key={`${pageNumber}-${index}`} className='flex items-center'>
              {pageNumber === '...' ? (
                <span className='px-1 text-sm text-muted-foreground'>...</span>
              ) : (
                <Button
                  variant={currentPage === pageNumber ? 'default' : 'outline'}
                  className='h-8 min-w-8 px-2'
                  onClick={() => onPageChange(pageNumber as number)}
                  disabled={disabled}
                >
                  <span className='sr-only'>Go to page {pageNumber}</span>
                  {pageNumber}
                </Button>
              )}
            </div>
          ))}

          <Button
            variant='outline'
            className='size-8 p-0'
            onClick={() => onPageChange(currentPage + 1)}
            disabled={!canNextPage}
          >
            <span className='sr-only'>Go to next page</span>
            <ChevronRightIcon className='h-4 w-4' />
          </Button>
          <Button
            variant='outline'
            className='size-8 p-0 @max-md/content:hidden'
            onClick={() => onPageChange(totalPages)}
            disabled={!canNextPage}
          >
            <span className='sr-only'>Go to last page</span>
            <DoubleArrowRightIcon className='h-4 w-4' />
          </Button>
        </div>
      </div>
    </div>
  )
}

type DataTablePaginationProps<TData> = {
  table: Table<TData>
  className?: string
}

/** <Pagination> driven by a client-side TanStack table. */
export function DataTablePagination<TData>({
  table,
  className,
}: DataTablePaginationProps<TData>) {
  const { pageIndex, pageSize } = table.getState().pagination

  return (
    <Pagination
      meta={{
        current_page: pageIndex + 1,
        last_page: table.getPageCount(),
        per_page: pageSize,
        total: table.getPrePaginationRowModel().rows.length,
      }}
      onPageChange={(page) => table.setPageIndex(page - 1)}
      onPageSizeChange={(size) => table.setPageSize(size)}
      className={className}
    />
  )
}
