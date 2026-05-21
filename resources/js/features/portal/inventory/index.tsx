import { useState } from 'react'
import { Search as SearchIcon, CheckCircle, Clock } from 'lucide-react'
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
import { usePortalInventory } from '@/hooks/usePortal'

const columns: ColumnDef<any>[] = [
  {
    accessorKey: 'product_code',
    header: 'Code',
    cell: ({ row }) => (
      <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold'>
        {row.getValue('product_code')}
      </code>
    ),
  },
  {
    accessorKey: 'name',
    header: 'Product Name',
    cell: ({ row }) => <span className='font-medium'>{row.getValue('name')}</span>,
  },
  {
    accessorKey: 'sku',
    header: 'SKU',
    cell: ({ row }) => <span className='text-sm'>{row.getValue('sku') || '—'}</span>,
  },
  {
    accessorKey: 'quantity',
    header: 'Quantity',
    cell: ({ row }) => <span className='font-medium'>{row.getValue('quantity')}</span>,
  },
  {
    accessorKey: 'unit_price',
    header: 'Unit Price',
    cell: ({ row }) => {
      const price = row.getValue('unit_price') as string | null
      return <span className='text-sm'>{price ? `SAR ${parseFloat(price).toFixed(2)}` : '—'}</span>
    },
  },
  {
    accessorKey: 'verification_status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('verification_status') as string
      return status === 'verified' ? (
        <Badge variant='secondary' className='bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 text-xs'>
          <CheckCircle className='mr-1 h-3 w-3' />
          Verified
        </Badge>
      ) : (
        <Badge variant='secondary' className='bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 text-xs'>
          <Clock className='mr-1 h-3 w-3' />
          Pending
        </Badge>
      )
    },
  },
  {
    accessorKey: 'created_at',
    header: 'Added',
    cell: ({ row }) => new Date(row.getValue('created_at')).toLocaleDateString(),
  },
]

export function PortalInventory() {
  const { products, meta, loading, filters, updateFilters } = usePortalInventory()
  const [search, setSearch] = useState('')

  const table = useReactTable({
    data: products,
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
          <h1 className='text-2xl font-bold tracking-tight'>My Inventory</h1>
          <p className='text-muted-foreground'>Track your products and stock levels</p>
        </div>

        <div className='space-y-4'>
          <div className='relative max-w-sm'>
            <SearchIcon className='absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground' />
            <Input
              placeholder='Search products...'
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
                      No products found.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>

          {meta && (
            <div className='text-sm text-muted-foreground'>
              Showing {meta.from} to {meta.to} of {meta.total} products
            </div>
          )}
        </div>
      </Main>
    </>
  )
}
