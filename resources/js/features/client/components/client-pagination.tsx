import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface ClientPaginationProps {
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  }
  onPageChange: (page: number) => void
  onPageSizeChange: (size: number) => void
}

export function ClientPagination({ meta, onPageChange, onPageSizeChange }: ClientPaginationProps) {
  return (
    <div className='flex items-center justify-between px-2'>
      <div className='flex-1 text-sm text-muted-foreground'>
        Showing {meta.from} to {meta.to} of {meta.total} clients
      </div>
      <div className='flex items-center space-x-6 lg:space-x-8'>
        <div className='flex items-center space-x-2'>
          <p className='text-sm font-medium'>Rows per page</p>
          <Select
            value={`${meta.per_page}`}
            onValueChange={(value) => onPageSizeChange(Number(value))}
          >
            <SelectTrigger className='h-8 w-[70px]'>
              <SelectValue placeholder={meta.per_page} />
            </SelectTrigger>
            <SelectContent side='top'>
              {[10, 15, 20, 30, 50].map((pageSize) => (
                <SelectItem key={pageSize} value={`${pageSize}`}>
                  {pageSize}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className='flex w-[100px] items-center justify-center text-sm font-medium'>
          Page {meta.current_page} of {meta.last_page}
        </div>
        <div className='flex items-center space-x-2'>
          <Button
            variant='outline'
            className='hidden h-8 w-8 p-0 lg:flex'
            onClick={() => onPageChange(1)}
            disabled={meta.current_page <= 1}
          >
            <ChevronsLeft className='h-4 w-4' />
          </Button>
          <Button
            variant='outline'
            className='h-8 w-8 p-0'
            onClick={() => onPageChange(meta.current_page - 1)}
            disabled={meta.current_page <= 1}
          >
            <ChevronLeft className='h-4 w-4' />
          </Button>
          <Button
            variant='outline'
            className='h-8 w-8 p-0'
            onClick={() => onPageChange(meta.current_page + 1)}
            disabled={meta.current_page >= meta.last_page}
          >
            <ChevronRight className='h-4 w-4' />
          </Button>
          <Button
            variant='outline'
            className='hidden h-8 w-8 p-0 lg:flex'
            onClick={() => onPageChange(meta.last_page)}
            disabled={meta.current_page >= meta.last_page}
          >
            <ChevronsRight className='h-4 w-4' />
          </Button>
        </div>
      </div>
    </div>
  )
}
