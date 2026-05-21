import { useState } from 'react'
import { Search as SearchIcon } from 'lucide-react'
import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalOrders } from '@/hooks/usePortal'

const columns: ColumnDef<any>[] = [
  {
    accessorKey: 'order_number',
    header: 'Order #',
    cell: ({ row }) => <span className='font-medium'>{row.getValue('order_number')}</span>,
  },
  {
    accessorKey: 'customer_name',
    header: 'Customer',
  },
  {
    accessorKey: 'fulfillment_status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('fulfillment_status') as string
      const colorMap: Record<string, string> = {
        fulfilled: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        unfulfilled: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
      }
      return <Badge variant='outline' className={`text-xs capitalize ${colorMap[status] || ''}`}>{status}</Badge>
    },
  },
  {
    accessorKey: 'financial_status',
    header: 'Payment',
    cell: ({ row }) => {
      const status = row.getValue('financial_status') as string
      const colorMap: Record<string, string> = {
        paid: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        refunded: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        partially_refunded: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
      }
      return <Badge variant='outline' className={`text-xs capitalize ${colorMap[status] || ''}`}>{status}</Badge>
    },
  },
  {
    accessorKey: 'total',
    header: 'Total',
    cell: ({ row }) => <span className='font-medium'>SAR {parseFloat(row.getValue('total')).toLocaleString()}</span>,
  },
  {
    accessorKey: 'created_at',
    header: 'Date',
    cell: ({ row }) => new Date(row.getValue('created_at')).toLocaleDateString(),
  },
]

export function PortalOrders() {
  const { orders, meta, loading, filters, updateFilters } = usePortalOrders()
  const [search, setSearch] = useState('')

  const table = useReactTable({
    data: orders,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: meta?.last_page || 0,
  })

  const handleSearch = (value: string) => {
    setSearch(value)
    updateFilters({ search: value, page: 1 })
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
        <div className='mb-4'>
          <h1 className='text-2xl font-bold tracking-tight'>My Orders</h1>
          <p className='text-muted-foreground'>Track all your orders</p>
        </div>

        <div className='space-y-4'>
          <div className='relative max-w-sm'>
            <SearchIcon className='absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground' />
            <Input
              placeholder='Search orders...'
              value={search}
              onChange={(e) => handleSearch(e.target.value)}
              className='pl-8'
            />
          </div>

          <div className='overflow-hidden rounded-md border'>
            <Table>
              <TableHeader>
                {table.getHeaderGroups().map((headerGroup) => (
                  <TableRow key={headerGroup.id}>
                    {headerGroup.headers.map((header) => (
                      <TableHead key={header.id}>
                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                      </TableHead>
                    ))}
                  </TableRow>
                ))}
              </TableHeader>
              <TableBody>
                {loading ? (
                  Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={i}>
                      {columns.map((_, j) => (
                        <TableCell key={j}><div className='h-4 w-full animate-pulse rounded bg-muted' /></TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : table.getRowModel().rows?.length ? (
                  table.getRowModel().rows.map((row) => (
                    <TableRow key={row.id}>
                      {row.getVisibleCells().map((cell) => (
                        <TableCell key={cell.id}>
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={columns.length} className='h-24 text-center'>
                      No orders found.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>

          {meta && (
            <div className='text-sm text-muted-foreground'>
              Showing {meta.from} to {meta.to} of {meta.total} orders
            </div>
          )}
        </div>
      </Main>
    </>
  )
}
