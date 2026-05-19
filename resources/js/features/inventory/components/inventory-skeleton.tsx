import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'

export function InventoryStatsSkeleton() {
  return (
    <div className='grid gap-3 md:grid-cols-2 lg:grid-cols-4'>
      {[...Array(4)].map((_, i) => (
        <Card key={i} className='border-muted/50'>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
            <Skeleton className='h-3 w-24' />
            <Skeleton className='h-5 w-5 rounded-full' />
          </CardHeader>
          <CardContent className='pb-0 -mt-3'>
            <Skeleton className='h-7 w-20 mb-1' />
            <Skeleton className='h-3 w-32' />
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

export function InventoryTableSkeleton() {
  return (
    <div className='space-y-3'>
      <div className='overflow-hidden rounded-md border border-muted/50'>
        <Table className='min-w-xl'>
          <TableHeader>
            <TableRow className='border-b border-muted/50 hover:bg-transparent'>
              {[40, 60, 180, 100, 80, 80, 60, 60, 32].map((w, i) => (
                <TableHead key={i} className='h-8 py-1.5'>
                  <Skeleton className={`h-3 w-[${w}px]`} />
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {[...Array(10)].map((_, i) => (
              <TableRow key={i} className='border-b border-muted/50 h-10'>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-4' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-8 w-8 rounded' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-44' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-24' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-20' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-16' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-5 w-16 rounded-full' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-16' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-4' /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
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

export function InventoryCardsSkeleton() {
  return (
    <div className='grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
      {[...Array(10)].map((_, i) => (
        <Card key={i} className='overflow-hidden border-muted/50'>
          <Skeleton className='h-48 w-full rounded-none' />
          <CardContent className='p-3 space-y-2'>
            <Skeleton className='h-4 w-full' />
            <Skeleton className='h-3 w-20' />
            <div className='flex items-center justify-between'>
              <Skeleton className='h-4 w-16' />
              <Skeleton className='h-5 w-14 rounded-full' />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

export function InventoryFiltersSkeleton() {
  return (
    <div className='flex flex-wrap items-center gap-2'>
      <Skeleton className='h-9 w-48' />
      <Skeleton className='h-9 w-32' />
      <Skeleton className='h-9 w-32' />
      <Skeleton className='h-9 w-24' />
      <div className='ml-auto flex gap-2'>
        <Skeleton className='h-9 w-9' />
        <Skeleton className='h-9 w-9' />
        <Skeleton className='h-9 w-20' />
      </div>
    </div>
  )
}
