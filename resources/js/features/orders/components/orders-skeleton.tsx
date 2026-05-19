import { Card, CardContent, CardHeader } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Skeleton } from '@/components/ui/skeleton'

export function OrdersStatsSkeleton() {
  return (
    <div className='grid gap-3 md:grid-cols-2 lg:grid-cols-4'>
      {[...Array(4)].map((_, i) => (
        <Card key={i} className='border-muted/50'>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
            <Skeleton className='h-3 w-20' />
            <Skeleton className='h-5 w-5 rounded-full' />
          </CardHeader>
          <CardContent className='pb-0 -mt-3'>
            <Skeleton className='h-7 w-24 mb-0' />
            <Skeleton className='h-3 w-32' />
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

export function OrdersTableSkeleton() {
  return (
    <div className='space-y-3'>
      {/* Table skeleton */}
      <div className='overflow-hidden rounded-md border border-muted/50'>
        <Table className='min-w-xl'>
          <TableHeader>
            <TableRow className='border-b border-muted/50 hover:bg-transparent'>
              <TableHead className='w-12 h-8 py-1.5'>
                <Skeleton className='h-4 w-4' />
              </TableHead>
              <TableHead className='h-8 py-1.5'>
                <Skeleton className='h-3 w-20' />
              </TableHead>
              <TableHead className='h-8 py-1.5'>
                <Skeleton className='h-3 w-24' />
              </TableHead>
              <TableHead className='h-8 py-1.5'>
                <Skeleton className='h-3 w-16' />
              </TableHead>
              <TableHead className='h-8 py-1.5'>
                <Skeleton className='h-3 w-20' />
              </TableHead>
              <TableHead className='h-8 py-1.5'>
                <Skeleton className='h-3 w-20' />
              </TableHead>
              <TableHead className='h-8 py-1.5'>
                <Skeleton className='h-3 w-16' />
              </TableHead>
              <TableHead className='h-8 py-1.5'>
                <Skeleton className='h-3 w-16' />
              </TableHead>
              <TableHead className='h-8 py-1.5 w-12' />
            </TableRow>
          </TableHeader>
          <TableBody>
            {[...Array(10)].map((_, i) => (
              <TableRow
                key={i}
                className='border-b border-muted/50 h-9'
              >
                <TableCell className='py-1.5'>
                  <Skeleton className='h-4 w-4' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-4 w-16' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-4 w-32' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-4 w-20' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-5 w-20 rounded-full' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-5 w-16 rounded-full' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-4 w-20' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-4 w-16' />
                </TableCell>
                <TableCell className='py-1.5'>
                  <Skeleton className='h-4 w-4' />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {/* Pagination skeleton */}
      <div className='flex items-center justify-between'>
        <Skeleton className='h-4 w-40' />
        <div className='flex items-center gap-2'>
          <Skeleton className='h-8 w-20' />
          <Skeleton className='h-4 w-16' />
          <Skeleton className='h-8 w-16' />
        </div>
      </div>
    </div>
  )
}

export function OrdersFiltersSkeleton() {
  return (
    <div className='flex flex-wrap items-center gap-2'>
      <Skeleton className='h-8 w-48' />
      <Skeleton className='h-8 w-32' />
      <Skeleton className='h-8 w-32' />
      <Skeleton className='h-8 w-24' />
      <div className='ml-auto flex gap-2'>
        <Skeleton className='h-8 w-8' />
        <Skeleton className='h-8 w-20' />
      </div>
    </div>
  )
}
